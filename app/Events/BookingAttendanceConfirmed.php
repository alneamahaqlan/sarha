<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired ONCE when a booking's attendance is first confirmed (the
 * null→stamped transition in BookingAttendanceService). Idempotent
 * re-confirms do NOT re-fire it.
 *
 * This is the integration seam for the rest of the customer-lifecycle
 * loop: later phases subscribe here rather than reaching into the
 * attendance flow.
 *   - Phase 1 (cashback): a listener evaluates the clinic's reward rule
 *     and mints a voucher for the customer.
 *   - Phase 2 (verified reviews): a listener invites the patient to
 *     rate the visit they're now confirmed to have attended.
 *
 * Plain internal event (NOT broadcast) — it carries domain context, not
 * a UI payload. The customer-facing "thanks for visiting" notification
 * is sent separately by the service via NotificationDispatcher.
 *
 * `$actorType`/`$actorId` capture who confirmed it (Clinic owner or
 * ClinicTeamMember), null for background/system confirmations.
 */
class BookingAttendanceConfirmed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public ?string $actorType = null,
        public ?int $actorId = null,
    ) {}
}
