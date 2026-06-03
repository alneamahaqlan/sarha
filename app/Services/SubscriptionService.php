<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\Subscription;
use App\Models\SubscriptionPackage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Encapsulates the "admin received a bank transfer → activate the
 * package for this clinic" flow. Doing the work in one place means
 * the controllers don't drift on date math and the clinic's
 * subscription_starts_at / subscription_ends_at / subscription_type
 * stays in sync with the latest active row.
 *
 * No payment handling here — the assumption (per spec) is the
 * admin already saw the proof of transfer and is recording it.
 */
class SubscriptionService
{
    public function __construct(
        private readonly SubscriptionLifecycleService $lifecycle,
    ) {}

    /**
     * Create a new active subscription for `$clinic` on `$package`
     * with the chosen cycle + bonus months, and sync the clinic row.
     *
     * `starts_at` rules:
     *   - if the clinic already has a future-dated active sub, the
     *     new one chains onto its ends_at (no overlap, no rollback)
     *   - otherwise, today
     */
    /**
     * @param ?float $amountOverride When provided, this exact amount is
     *        charged instead of the package-derived default. This is the
     *        per-subscription manual price: the package stays the source
     *        of truth for features + the default price, but an admin can
     *        negotiate a custom amount for a specific clinic/subscription.
     */
    public function activate(
        Clinic $clinic,
        SubscriptionPackage $package,
        string $cycle = Subscription::CYCLE_QUARTERLY,
        int $bonusMonths = 0,
        ?int $adminId = null,
        ?string $notes = null,
        ?float $amountOverride = null,
    ): Subscription {
        $bonusMonths = max(0, min($bonusMonths, 24));

        return DB::transaction(function () use ($clinic, $package, $cycle, $bonusMonths, $adminId, $notes, $amountOverride) {
            // Pessimistic row lock so two concurrent activations (double-
            // click, or two admins clicking at once) serialise instead of
            // each computing the same starts_at and producing overlapping
            // active rows. The lock is released at txn commit/rollback.
            $clinic = Clinic::lockForUpdate()->find($clinic->id);

            $startsAt = $this->startsAtFor($clinic);
            $months = ($cycle === Subscription::CYCLE_ANNUAL ? 12 : 3) + $bonusMonths;
            $endsAt = CarbonImmutable::parse($startsAt)->addMonthsNoOverflow($months);

            // amount = base * paid months (the bonus months are FREE,
            // so they don't multiply into the price). Annual without
            // bonus → 12 × monthly_price.
            $paidMonths = $cycle === Subscription::CYCLE_ANNUAL ? 12 : 3;
            $amount = $amountOverride !== null
                ? max(0.0, $amountOverride)
                : (float) $package->monthly_price * $paidMonths;

            $sub = Subscription::create([
                'clinic_id'               => $clinic->id,
                'subscription_package_id' => $package->id,
                'type'                    => $package->slug, // legacy denormalised
                'billing_cycle'           => $cycle,
                'bonus_months'            => $bonusMonths,
                'amount'                  => $amount,
                'starts_at'               => $startsAt,
                'ends_at'                 => $endsAt,
                'status'                  => 'active',
                'created_by_admin_id'     => $adminId,
                'notes'                   => $notes,
            ]);

            $this->syncClinic($clinic);

            $fresh = $sub->fresh();
            // Fire-and-forget bell + push so the clinic owner sees a
            // receipt the instant the admin clicks "save". Failures
            // are swallowed by the dispatcher itself.
            $this->lifecycle->dispatchActivated($clinic, $fresh);
            return $fresh;
        });
    }

    /**
     * Renew: shorthand for `activate(currentPackage, currentCycle)` —
     * lets the admin click one button on a row without having to re-pick
     * the same package the clinic is already on.
     */
    public function renew(Subscription $previous, ?int $adminId = null, ?string $notes = null): Subscription
    {
        $clinic = $previous->clinic;
        $package = $previous->package ?? SubscriptionPackage::find($previous->subscription_package_id);
        if (! $clinic || ! $package) {
            throw new \RuntimeException('Cannot renew a subscription whose clinic/package is missing.');
        }
        return $this->activate(
            $clinic,
            $package,
            $previous->billing_cycle ?: Subscription::CYCLE_QUARTERLY,
            (int) ($previous->bonus_months ?? 0),
            $adminId,
            $notes ?? __('admin.subscriptions.renewal_note', ['ref' => $previous->id]),
            // Carry the previous (possibly custom-negotiated) amount forward
            // so a clinic on a manual price keeps it across renewals instead
            // of snapping back to the package default.
            amountOverride: $previous->amount !== null ? (float) $previous->amount : null,
        );
    }

    /**
     * Cancel: marks the sub cancelled and ends it at today. If this
     * was the clinic's current active subscription, the clinic row
     * is re-synced so its `subscription_ends_at` reflects the change.
     */
    public function cancel(Subscription $subscription): Subscription
    {
        DB::transaction(function () use ($subscription) {
            $subscription->update([
                'status'   => 'cancelled',
                'ends_at'  => now(),
            ]);
            if ($subscription->clinic) {
                $this->syncClinic($subscription->clinic);
            }
        });
        $fresh = $subscription->fresh();
        if ($fresh->clinic) {
            $this->lifecycle->dispatchCancelled($fresh->clinic, $fresh);
        }
        return $fresh;
    }

    /**
     * Re-derive the clinic's denormalised subscription fields from
     * its latest non-cancelled subscription. Idempotent — safe to
     * call after any change to that clinic's subs.
     *
     * If ALL subs are cancelled, we still need to mirror the most-
     * recently-cancelled row so the clinic's `subscription_ends_at`
     * reflects the cancellation date (otherwise `cancel()` leaves the
     * clinic looking active until the original expiry rolls around).
     */
    public function syncClinic(Clinic $clinic): void
    {
        $latest = Subscription::where('clinic_id', $clinic->id)
            ->whereIn('status', ['active', 'expired'])
            ->orderByDesc('ends_at')
            ->first();

        // No active/expired row? Fall back to the most recently
        // cancelled one so the clinic stops looking subscribed.
        if (! $latest) {
            $latest = Subscription::where('clinic_id', $clinic->id)
                ->where('status', 'cancelled')
                ->orderByDesc('ends_at')
                ->first();
        }

        if (! $latest) {
            return; // truly no history — leave the clinic as is
        }

        $package = $latest->package ?? SubscriptionPackage::find($latest->subscription_package_id);
        $clinic->update([
            'subscription_package_id' => $latest->subscription_package_id,
            'subscription_type'       => $package?->slug ?? $latest->type,
            'subscription_starts_at'  => $latest->starts_at,
            'subscription_ends_at'    => $latest->ends_at,
        ]);
    }

    /**
     * Today if the clinic has no current active sub; otherwise the
     * end of its current active sub so the new one chains cleanly.
     */
    private function startsAtFor(Clinic $clinic): string
    {
        $current = Subscription::where('clinic_id', $clinic->id)
            ->where('status', 'active')
            ->where('ends_at', '>=', now())
            ->orderByDesc('ends_at')->first();

        return ($current?->ends_at ?? now())->toDateTimeString();
    }
}
