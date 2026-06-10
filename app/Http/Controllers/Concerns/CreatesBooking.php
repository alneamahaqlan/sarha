<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Booking;
use App\Models\Clinic;
use App\Models\ClinicStat;

/**
 * Shared public booking-creation path. Extracted verbatim from
 * ClinicController::createBooking so the public clinic form and the landing
 * pages both create bookings through the exact same code — which means the
 * BookingCustomerLinkObserver fires identically and no duplicate Customer /
 * PlatformCustomer rows are ever created.
 *
 * The optional $attribution array additively stamps marketing attribution
 * (landing_page_id, utm_*, acquisition_source) without changing the core
 * behaviour for the existing clinic flow, which passes nothing.
 *
 * Consumers must also use the IdentifiesCustomer trait (for resolveCustomerUser).
 */
trait CreatesBooking
{
    /** Create the booking, attaching/creating the customer user for persistence. */
    protected function createBooking(Clinic $clinic, array $data, array $attribution = []): Booking
    {
        // user_id is the account that OWNS the booking (where it shows
        // up under /account/bookings). For relative-mode it's the
        // booker, not the relative — spec: "القريب نفسه لا يرى الحجز
        // في حسابه (لتجنّب الازدواجية)".
        $relativeId = $data['relative_id'] ?? null;
        $bookerId   = $data['booker_user_id'] ?? null;

        if ($relativeId) {
            // Relative-mode always carries booker_user_id (resolveBookingTarget
            // sets both). user_id = the booker so the booking is filed
            // under the right account.
            $userId = $bookerId;
        } elseif (array_key_exists('user_id', $attribution)) {
            // Caller pinned the owning account explicitly. Landing pages use
            // this to file a frictionless guest booking with user_id = null
            // (no account yet) — the observer still creates the per-clinic
            // Customer + an UNVERIFIED PlatformCustomer by phone, so the
            // post-booking "register now" OTP links to it without duplicates.
            $userId = $attribution['user_id'];
        } else {
            // Self-mode: auth user OR a freshly-resolved customer from
            // the typed phone (guest path, same as before).
            $userId = auth('web')->id()
                ?? $this->resolveCustomerUser($data['customer_name'], $data['customer_phone'])->id;
        }

        $booking = Booking::create([
            'clinic_id'      => $clinic->id,
            'user_id'        => $userId,
            'booker_user_id' => $bookerId,
            'relative_id'    => $data['relative_id'] ?? null,
            'service_id'     => $data['service_id'] ?? null,
            'customer_name'  => $data['customer_name'],
            'customer_phone' => $data['customer_phone'],
            'notes'          => $data['notes'] ?? null,
            'status'         => 'new',
            'source'         => 'website',
            // Additive marketing attribution — empty for the clinic flow.
            'acquisition_source' => $attribution['acquisition_source'] ?? 'other',
            'landing_page_id'    => $attribution['landing_page_id'] ?? null,
            'utm_source'         => $attribution['utm_source'] ?? null,
            'utm_medium'         => $attribution['utm_medium'] ?? null,
            'utm_campaign'       => $attribution['utm_campaign'] ?? null,
            'utm_content'        => $attribution['utm_content'] ?? null,
            'utm_term'           => $attribution['utm_term'] ?? null,
        ]);

        ClinicStat::bump($clinic->id, 'bookings_count');

        return $booking;
    }
}
