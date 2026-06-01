<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Complaint;
use App\Models\Customer;
use App\Models\PriceQuoteRequest;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\Log;

/**
 * Single entry point for resolving "which Customer row owns this
 * booking / complaint / quote?" — used by the three create-time
 * observers.
 *
 * Identity rules:
 *   - Bookings with booker_user_id (proxy bookings — حجز لقريب) are
 *     attributed to the BOOKER (the account holder), not the patient.
 *     Per product call: the relative's phone lives in the booking row
 *     but the Customer represents the long-term relationship with the
 *     booker.
 *   - All other rows dedupe by the row's own customer_phone after
 *     normalization to +9665XXXXXXXX (E.164).
 *
 * Side effects: increments the matching denormalized counters and
 * stamps last_seen_at / last_interaction_*. Atomic via Eloquent's
 * ->increment() so concurrent writes don't drift.
 */
class CustomerLinker
{
    /**
     * Resolve (or create) the Customer for a Booking. Returns the
     * Customer; caller is expected to assign $booking->customer_id
     * during the `creating` hook. Throws nothing — falls back to
     * raw input when normalization can't make sense of the phone.
     */
    public function forBooking(Booking $booking): ?Customer
    {
        [$phone, $name, $userId, $email] = $this->resolveBookingIdentity($booking);
        return $this->findOrCreate($booking->clinic_id, $phone, $name, $email, $userId);
    }

    public function forComplaint(Complaint $complaint): ?Customer
    {
        $phone = PhoneNormalizer::normalizeOrSelf($complaint->customer_phone);
        if (! $phone) return null;
        return $this->findOrCreate(
            $complaint->clinic_id,
            $phone,
            $complaint->customer_name ?? '—',
            $complaint->customer_email,
            $complaint->user_id,
        );
    }

    public function forPriceQuote(PriceQuoteRequest $quote): ?Customer
    {
        $phone = PhoneNormalizer::normalizeOrSelf($quote->customer_phone);
        if (! $phone) return null;
        return $this->findOrCreate(
            $quote->clinic_id,
            $phone,
            $quote->customer_name ?? '—',
            null, // price_quote_requests has no email column
            $quote->user_id,
        );
    }

    /**
     * Per-row identity resolver for bookings: proxy bookings inherit
     * the booker's identity; direct bookings use the row's own phone.
     *
     * @return array{0:?string,1:string,2:?int,3:?string}
     */
    private function resolveBookingIdentity(Booking $booking): array
    {
        if ($booking->booker_user_id) {
            $booker = User::find($booking->booker_user_id);
            if ($booker) {
                return [
                    PhoneNormalizer::normalizeOrSelf($booker->phone),
                    $booker->name ?? '—',
                    $booker->id,
                    $booker->email,
                ];
            }
        }
        return [
            PhoneNormalizer::normalizeOrSelf($booking->customer_phone),
            $booking->customer_name ?? '—',
            $booking->user_id,
            null,
        ];
    }

    /**
     * Upsert by (clinic_id, phone). Auto-links to a User row when one
     * shares the normalized phone. Refreshes name/email when callers
     * provide new values (the latest interaction wins).
     */
    private function findOrCreate(?int $clinicId, ?string $phone, string $name, ?string $email, ?int $userId): ?Customer
    {
        // General complaints / broadcast price-quotes carry no clinic, and a
        // Customer is keyed by (clinic_id, phone) — there's nothing to link to.
        // Skip silently instead of blowing up the create with a TypeError.
        if (! $clinicId || ! $phone) return null;

        // Auto-link to platform User if the caller didn't already pass
        // one — phone match is the only signal we have at this layer.
        if (! $userId) {
            $userId = User::where('phone', $phone)->value('id');
        }

        $customer = Customer::firstOrCreate(
            ['clinic_id' => $clinicId, 'phone' => $phone],
            [
                'name'          => $name,
                'email'         => $email,
                'user_id'       => $userId,
                'first_seen_at' => now(),
            ]
        );

        // If the row existed but lacked name/email/user_id we just
        // resolved, top it up. Never overwrite a non-null email/user_id
        // with a null (don't lose information).
        $patch = [];
        if (! $customer->name && $name) $patch['name'] = $name;
        if (! $customer->email && $email) $patch['email'] = $email;
        if (! $customer->user_id && $userId) $patch['user_id'] = $userId;
        if ($patch) {
            $customer->forceFill($patch)->save();
        }

        return $customer;
    }

    /**
     * Stamp a new interaction. Called from the *created* hook of each
     * observer. Atomic increments + a single save for the timestamp
     * pair.
     */
    public function recordInteraction(Customer $customer, string $type, ?\DateTimeInterface $when = null): void
    {
        $when ??= now();
        try {
            $customer->forceFill([
                'last_interaction_at'   => $when,
                'last_interaction_type' => $type,
                'last_seen_at'          => $when,
            ])->save();

            match ($type) {
                Customer::TYPE_BOOKING       => $customer->increment('total_bookings'),
                Customer::TYPE_COMPLAINT     => $customer->increment('total_complaints'),
                Customer::TYPE_QUOTE_REQUEST => $customer->increment('total_quote_requests'),
                default                      => null,
            };
        } catch (\Throwable $e) {
            // Counter maintenance must never break the underlying write.
            Log::warning('CustomerLinker::recordInteraction failed', [
                'customer' => $customer->id,
                'type'     => $type,
                'err'      => $e->getMessage(),
            ]);
        }
    }

    public function markBookingCompleted(Customer $customer): void
    {
        try { $customer->increment('completed_bookings'); }
        catch (\Throwable $e) {
            Log::warning('CustomerLinker::markBookingCompleted failed', [
                'customer' => $customer->id, 'err' => $e->getMessage(),
            ]);
        }
    }
}
