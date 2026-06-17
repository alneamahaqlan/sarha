<?php

namespace App\Services;

use App\Enums\NotificationEvent;
use App\Models\Booking;
use App\Models\Clinic;
use App\Models\ClinicRewardRule;
use App\Models\Customer;
use App\Models\RewardVoucher;
use App\Models\User;
use App\Support\ActingClinicUser;
use App\Support\PhoneNormalizer;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

/**
 * The cashback-rewards engine. Owns every voucher state transition:
 * minting (auto on attendance / manual), redeeming, transferring,
 * voiding, and bulk expiry. Vouchers are NOT money — they reference a
 * specific offer (discount) or service (free) and are locked to the
 * issuing clinic.
 *
 * Failures here must never break the originating action (an attendance
 * confirmation must succeed even if reward minting fails) — the
 * attendance listeners wrap calls and the service logs and swallows.
 */
class RewardService
{
    public function __construct(
        private readonly FeatureGate $features,
        private readonly NotificationDispatcher $notifications,
        private readonly PlatformCustomerResolver $platform,
        private readonly ClinicActivityLogger $activity,
        private readonly SmsService $sms,
    ) {}

    /**
     * Mint a voucher for an attendance-confirmed booking, per the
     * clinic's auto-grant rule. Returns the voucher, or null when the
     * feature is off, the rule isn't grantable, or one was already
     * minted for this booking (idempotent).
     */
    public function grantFromAttendance(Booking $booking): ?RewardVoucher
    {
        $clinic = $booking->clinic;
        if (! $clinic || ! $this->features->hasRewardsAccess($clinic)) {
            return null;
        }

        $rule = ClinicRewardRule::where('clinic_id', $clinic->id)->first();
        if (! $rule || ! $rule->isGrantable()) {
            return null;
        }

        // Idempotency: one auto voucher per booking. A prior voucher that
        // was voided (attendance revoked then re-confirmed) does NOT block
        // a fresh grant; an active/used/expired one does.
        $already = RewardVoucher::where('origin_booking_id', $booking->id)
            ->where('source', RewardVoucher::SOURCE_ATTENDANCE)
            ->where('status', '!=', RewardVoucher::STATUS_VOID)
            ->exists();
        if ($already) {
            return null;
        }

        [$platformId, $customerId, $phone] = $this->resolveOwnerFromBooking($booking);

        $voucher = RewardVoucher::create([
            'clinic_id'            => $clinic->id,
            'platform_customer_id' => $platformId,
            'customer_id'          => $customerId,
            'phone'                => $phone,
            'type'                 => $rule->type,
            'offer_id'             => $rule->type === RewardVoucher::TYPE_OFFER_DISCOUNT ? $rule->offer_id : null,
            'service_id'           => $rule->type === RewardVoucher::TYPE_FREE_SERVICE ? $rule->service_id : null,
            'discount_type'        => $rule->type === RewardVoucher::TYPE_OFFER_DISCOUNT ? $rule->discount_type : null,
            'discount_value'       => $rule->type === RewardVoucher::TYPE_OFFER_DISCOUNT ? $rule->discount_value : null,
            'status'               => RewardVoucher::STATUS_ACTIVE,
            'source'               => RewardVoucher::SOURCE_ATTENDANCE,
            'origin_booking_id'    => $booking->id,
            'expires_at'           => $rule->validity_days ? now()->addDays($rule->validity_days) : null,
        ]);

        $this->activity->log('reward.granted_auto', $voucher, [
            'code'      => $voucher->code,
            'reference' => $booking->reference_code,
            'customer'  => $booking->customer_name,
        ]);

        $this->notifyGranted($voucher, $clinic, $booking->user);

        return $voucher;
    }

    /**
     * Manually grant a voucher to a single phone (the clinic gifting a
     * customer/segment). Resolves/creates the recipient identity so it
     * lands in their account, and stamps the granting actor.
     */
    public function grantManual(Clinic $clinic, string $phone, array $reward, ?CarbonInterface $expiresAt = null): RewardVoucher
    {
        $phone = PhoneNormalizer::normalizeOrSelf($phone) ?? $phone;
        [$platformId, $customerId] = $this->resolveOwnerForPhone($clinic, $phone);

        $type = $reward['type'];
        $voucher = RewardVoucher::create([
            'clinic_id'            => $clinic->id,
            'platform_customer_id' => $platformId,
            'customer_id'          => $customerId,
            'phone'                => $phone,
            'type'                 => $type,
            'offer_id'             => $type === RewardVoucher::TYPE_OFFER_DISCOUNT ? ($reward['offer_id'] ?? null) : null,
            'service_id'           => $type === RewardVoucher::TYPE_FREE_SERVICE ? ($reward['service_id'] ?? null) : null,
            'discount_type'        => $type === RewardVoucher::TYPE_OFFER_DISCOUNT ? ($reward['discount_type'] ?? null) : null,
            'discount_value'       => $type === RewardVoucher::TYPE_OFFER_DISCOUNT ? ($reward['discount_value'] ?? null) : null,
            'status'               => RewardVoucher::STATUS_ACTIVE,
            'source'               => RewardVoucher::SOURCE_MANUAL,
            'expires_at'           => $expiresAt,
            'granted_by_type'      => ActingClinicUser::actorType(),
            'granted_by_id'        => ActingClinicUser::actorId(),
            'granted_by_name'      => ActingClinicUser::actorName() ?? '—',
        ]);

        $this->activity->log('reward.granted_manual', $voucher, ['code' => $voucher->code, 'phone' => $phone]);
        $this->notifyGranted($voucher, $clinic, $this->userForPlatform($platformId));

        return $voucher;
    }

    /**
     * The single redemption gate — every redeem/apply path runs through
     * it so a voucher is never spent on the wrong clinic, when inactive/
     * expired, or against a booking whose service/offer doesn't match.
     * Throws \RuntimeException('reward_*') on the first failing rule;
     * the i18n key mirrors the reason so callers surface a clear message.
     */
    public function ensureRedeemable(RewardVoucher $voucher, Clinic $clinic, ?Booking $booking = null): void
    {
        if ((int) $voucher->clinic_id !== (int) $clinic->id) {
            throw new \RuntimeException('reward_wrong_clinic');
        }
        if ($voucher->status !== RewardVoucher::STATUS_ACTIVE) {
            throw new \RuntimeException('reward_not_active');
        }
        if ($voucher->isExpired()) {
            $voucher->update(['status' => RewardVoucher::STATUS_EXPIRED]);
            throw new \RuntimeException('reward_expired');
        }

        // Type/target match only matters when a booking is in play — a
        // standalone reception redemption (verbal verification) skips it.
        if ($booking) {
            if ($voucher->type === RewardVoucher::TYPE_FREE_SERVICE) {
                if (! $voucher->service_id || (int) $booking->service_id !== (int) $voucher->service_id) {
                    throw new \RuntimeException('reward_service_mismatch');
                }
            } else { // offer_discount — the offer must be a service-type offer for the same service
                $offerServiceId = $voucher->offer?->service_id;
                if (! $offerServiceId || (int) $booking->service_id !== (int) $offerServiceId) {
                    throw new \RuntimeException('reward_offer_mismatch');
                }
            }
        }
    }

    /**
     * Reserve (apply) a voucher to a booking without consuming it — the
     * customer "applies" it at booking time; it stays active until
     * reception redeems it at the visit. Overwrites any prior reservation.
     */
    public function apply(RewardVoucher $voucher, Booking $booking): RewardVoucher
    {
        $this->ensureRedeemable($voucher, $booking->clinic, $booking);

        $voucher->update(['applied_booking_id' => $booking->id]);
        $this->activity->log('reward.applied', $voucher, [
            'code'      => $voucher->code,
            'reference' => $booking->reference_code,
        ]);

        return $voucher;
    }

    /**
     * Guarded redemption — the ONLY path controllers may call. Runs the
     * gate, then consumes the voucher. Falls back to the reserved
     * (applied) booking when the caller doesn't pass one.
     */
    public function redeemChecked(RewardVoucher $voucher, Clinic $clinic, ?Booking $booking = null): bool
    {
        $booking ??= $voucher->applied_booking_id ? $voucher->appliedBooking : null;
        $this->ensureRedeemable($voucher, $clinic, $booking);

        return $this->redeem($voucher, $booking);
    }

    /**
     * Redeem a voucher (single-use). Expired-but-still-active rows are
     * flipped to expired and rejected. Returns true on a successful
     * redemption. Internal — go through redeemChecked() from controllers.
     */
    public function redeem(RewardVoucher $voucher, ?Booking $booking = null): bool
    {
        if ($voucher->status !== RewardVoucher::STATUS_ACTIVE) {
            return false;
        }
        if ($voucher->isExpired()) {
            $voucher->update(['status' => RewardVoucher::STATUS_EXPIRED]);
            return false;
        }

        $voucher->update([
            'status'              => RewardVoucher::STATUS_USED,
            'used_at'             => now(),
            'redeemed_booking_id' => $booking?->id,
        ]);

        $this->activity->log('reward.redeemed', $voucher, [
            'code'      => $voucher->code,
            'reference' => $booking?->reference_code,
        ]);

        return true;
    }

    /**
     * Transfer an active voucher to another phone. Stays locked to the
     * same clinic with the same expiry; ownership moves to the target's
     * platform identity (created if new). Throws when the voucher isn't
     * transferable or the target is the current owner.
     */
    public function transfer(RewardVoucher $voucher, string $toPhone): RewardVoucher
    {
        if (! $voucher->isActive()) {
            throw new \RuntimeException('reward_not_transferable');
        }

        $normalized = PhoneNormalizer::normalizeOrSelf($toPhone);
        if (! $normalized) {
            throw new \RuntimeException('reward_invalid_phone');
        }

        [$platformId, $customerId] = $this->resolveOwnerForPhone($voucher->clinic, $normalized);
        if ($platformId && $platformId === $voucher->platform_customer_id) {
            throw new \RuntimeException('reward_transfer_to_self');
        }

        $fromPhone = $voucher->phone;
        $voucher->update([
            'platform_customer_id'   => $platformId,
            'customer_id'            => $customerId,
            'phone'                  => $normalized,
            'transferred_at'         => now(),
            'transferred_from_phone' => $fromPhone,
        ]);

        $this->activity->log('reward.transferred', $voucher, [
            'code' => $voucher->code,
            'from' => $fromPhone,
            'to'   => $normalized,
        ]);

        $target = $this->userForPlatform($platformId);
        if ($target) {
            // Recipient has an account — ring the in-app bell + web push.
            $this->notifications->dispatch(NotificationEvent::REWARD_TRANSFERRED, $target, [
                'clinic' => $voucher->clinic?->name,
                'code'   => $voucher->code,
            ]);
        } else {
            // No account yet — the voucher is invisible to them until they
            // sign up, so nudge out-of-band via SMS to claim it.
            try {
                $this->sms->send($normalized, __('site.reward_gift_sms', [
                    'clinic' => $voucher->clinic?->name ?? '',
                ]));
            } catch (\Throwable $e) {
                Log::warning('RewardService::transfer SMS failed', ['voucher' => $voucher->id, 'err' => $e->getMessage()]);
            }
        }

        return $voucher;
    }

    /** Void an active voucher (e.g. its attendance was revoked). */
    public function void(RewardVoucher $voucher): bool
    {
        if ($voucher->status !== RewardVoucher::STATUS_ACTIVE) {
            return false;
        }
        $voucher->update(['status' => RewardVoucher::STATUS_VOID]);
        $this->activity->log('reward.voided', $voucher, ['code' => $voucher->code]);
        return true;
    }

    /**
     * Void any active voucher minted by a booking whose attendance was
     * revoked (used vouchers are left untouched). The symmetric
     * counterpart of grantFromAttendance.
     */
    public function voidFromRevokedAttendance(Booking $booking): int
    {
        $vouchers = RewardVoucher::where('origin_booking_id', $booking->id)
            ->where('source', RewardVoucher::SOURCE_ATTENDANCE)
            ->where('status', RewardVoucher::STATUS_ACTIVE)
            ->get();

        foreach ($vouchers as $voucher) {
            $this->void($voucher);
        }
        return $vouchers->count();
    }

    /** Bulk-expire active vouchers past their expiry. Returns the count. */
    public function expireDue(): int
    {
        return RewardVoucher::query()
            ->where('status', RewardVoucher::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => RewardVoucher::STATUS_EXPIRED]);
    }

    // ---------- internals ----------

    /**
     * Owner triple (platform_customer_id, customer_id, phone) for an
     * attended booking. Prefers the per-clinic Customer already linked by
     * BookingCustomerLinkObserver; resolves the platform identity as a
     * fallback so guests still get an owned voucher keyed by phone.
     *
     * @return array{0:?int,1:?int,2:string}
     */
    private function resolveOwnerFromBooking(Booking $booking): array
    {
        $customer = $booking->customer;
        $phone = $customer?->phone ?? PhoneNormalizer::normalizeOrSelf($booking->customer_phone) ?? $booking->customer_phone;

        $platformId = $customer?->platform_customer_id;
        if (! $platformId) {
            $platform = $this->platform->record(
                $phone,
                $booking->customer_name ?? '—',
                \App\Models\CustomerNameAlias::SOURCE_SELF,
                $booking->clinic_id,
                $booking->user_id,
            );
            $platformId = $platform?->id;
        }

        return [$platformId, $customer?->id, $phone];
    }

    /**
     * Resolve (platform_customer_id, customer_id) for a phone under a
     * clinic — creating both identity rows if absent — so a manually
     * granted or transferred voucher lands in the recipient's account.
     *
     * @return array{0:?int,1:?int}
     */
    private function resolveOwnerForPhone(Clinic $clinic, string $phone): array
    {
        $platform = $this->platform->record($phone, '—', \App\Models\CustomerNameAlias::SOURCE_SELF, $clinic->id);

        $customer = Customer::firstOrCreate(
            ['clinic_id' => $clinic->id, 'phone' => $phone],
            [
                'platform_customer_id' => $platform?->id,
                'name'                 => $platform?->displayName() ?? '—',
                'first_seen_at'        => now(),
            ],
        );
        if (! $customer->platform_customer_id && $platform) {
            $customer->forceFill(['platform_customer_id' => $platform->id])->save();
        }

        return [$platform?->id, $customer->id];
    }

    /** The platform account (User) behind an identity, if it has one. */
    private function userForPlatform(?int $platformId): ?User
    {
        if (! $platformId) {
            return null;
        }
        return \App\Models\PlatformCustomer::find($platformId)?->user;
    }

    private function notifyGranted(RewardVoucher $voucher, Clinic $clinic, ?User $user): void
    {
        if (! $user) {
            return;
        }
        try {
            $this->notifications->dispatch(NotificationEvent::REWARD_GRANTED, $user, [
                'clinic' => $clinic->name,
                'code'   => $voucher->code,
            ]);
        } catch (\Throwable $e) {
            Log::warning('RewardService::notifyGranted failed', ['voucher' => $voucher->id, 'err' => $e->getMessage()]);
        }
    }
}
