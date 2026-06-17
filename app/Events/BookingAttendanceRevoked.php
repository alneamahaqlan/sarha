<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired ONCE when a booking's confirmed attendance is cleared (the
 * stamped→null transition in BookingAttendanceService::revoke). The
 * symmetric counterpart to BookingAttendanceConfirmed — idempotent
 * no-op revokes do NOT fire it.
 *
 * The integration seam for UNDOING lifecycle side effects: when phase 1
 * grants a cashback voucher on the confirm event, a listener here voids
 * the matching voucher so a reception mis-click doesn't leave a reward
 * standing. Keeping confirm/revoke symmetric means later phases only
 * add listeners — they never reach back into the attendance flow.
 *
 * `$actorType`/`$actorId` capture who revoked it (Clinic owner or
 * ClinicTeamMember), null for background/system revocations.
 */
class BookingAttendanceRevoked
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public ?string $actorType = null,
        public ?int $actorId = null,
    ) {}
}
