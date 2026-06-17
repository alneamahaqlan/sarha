<?php

namespace App\Services;

use App\Enums\NotificationEvent;
use App\Events\BookingAttendanceConfirmed;
use App\Events\BookingAttendanceRevoked;
use App\Models\Booking;
use App\Support\ActingClinicUser;

/**
 * Owns the attendance signal on a booking — confirming or revoking that
 * the patient physically showed up. Attendance is INDEPENDENT of the
 * status enum and the Kanban: this service never touches `status`,
 * `stage_id`, or any board state.
 *
 * Confirming is idempotent: stamping an already-attended booking is a
 * no-op (no double event, no duplicate "thanks for visiting"). The
 * null→stamped transition is the single point that fires
 * BookingAttendanceConfirmed — the seam the cashback (phase 1) and
 * verified-review (phase 2) listeners hook into.
 */
class BookingAttendanceService
{
    public function __construct(
        private readonly ClinicActivityLogger $activity,
        private readonly NotificationDispatcher $notifications,
    ) {}

    /**
     * Confirm the patient attended. Returns true on the first
     * confirmation (the transition happened), false if it was already
     * confirmed (idempotent no-op).
     */
    public function confirm(Booking $booking): bool
    {
        if ($booking->isAttended()) {
            return false;
        }

        // Stamp the signal + who confirmed it (Clinic owner or team
        // member), mirroring the created_by snapshot so the credit
        // survives if the member row is later removed. Stays null for
        // background runs with no clinic session.
        $actorType = ActingClinicUser::actorType();
        $actorId   = ActingClinicUser::actorId();

        $booking->update([
            'attended_at'      => now(),
            'attended_by_type' => $actorType,
            'attended_by_id'   => $actorId,
            'attended_by_name' => ActingClinicUser::actorName() ?? '—',
        ]);

        $this->activity->log('booking.attendance_confirmed', $booking, [
            'reference'      => $booking->reference_code,
            'customer'       => $booking->customer_name,
            'customer_phone' => $booking->customer_phone,
        ]);

        // Domain seam — later phases (cashback, verified reviews)
        // subscribe here. Fired once, only on the null→stamped edge.
        BookingAttendanceConfirmed::dispatch($booking, $actorType, $actorId);

        // Gentle "thanks for visiting" to the account holder, when the
        // booking is linked to one. Guest / walk-in rows (no user_id)
        // have no bell to ring — the lifecycle reward still flows via
        // the domain event above.
        if ($booking->user_id && ($user = $booking->user)) {
            $this->notifications->dispatch(NotificationEvent::BOOKING_ATTENDED, $user, [
                'clinic'         => $booking->clinic?->name,
                'reference_code' => $booking->reference_code,
            ]);
        }

        return true;
    }

    /**
     * Revoke a mistaken attendance stamp (reception mis-click). Returns
     * true if a stamp was cleared, false if there was nothing to clear.
     *
     * Fires BookingAttendanceRevoked (symmetric to confirm) so later
     * phases can undo their side effects via a listener — e.g. phase 1
     * voids the cashback voucher it minted on confirmation — instead of
     * this method having to know about them.
     */
    public function revoke(Booking $booking): bool
    {
        if (! $booking->isAttended()) {
            return false;
        }

        $actorType = ActingClinicUser::actorType();
        $actorId   = ActingClinicUser::actorId();

        $booking->update([
            'attended_at'      => null,
            'attended_by_type' => null,
            'attended_by_id'   => null,
            'attended_by_name' => null,
        ]);

        $this->activity->log('booking.attendance_revoked', $booking, [
            'reference'      => $booking->reference_code,
            'customer'       => $booking->customer_name,
            'customer_phone' => $booking->customer_phone,
        ]);

        // Symmetric seam — fired once on the stamped→null edge.
        BookingAttendanceRevoked::dispatch($booking, $actorType, $actorId);

        return true;
    }
}
