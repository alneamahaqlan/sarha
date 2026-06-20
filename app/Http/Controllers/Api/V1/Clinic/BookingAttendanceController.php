<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BookingResource as BookingApiResource;
use App\Models\Booking;
use App\Services\BookingAttendanceService;

/**
 * Confirms / revokes the patient's physical attendance for a booking —
 * an independent signal alongside the status enum (it never changes
 * status or the Kanban column). Reception confirms attendance from the
 * bookings list; the stamp opens the customer-lifecycle loop (cashback,
 * verified reviews) via the BookingAttendanceConfirmed event.
 *
 * Clinic isolation is enforced by the `update` policy ($actor owns the
 * booking) — same gate as editing a booking.
 */
class BookingAttendanceController extends Controller
{
    public function __construct(
        private readonly BookingAttendanceService $attendance,
    ) {}

    /** Confirm attendance. Idempotent — re-confirming is a safe no-op. */
    public function confirm(Booking $booking): BookingApiResource
    {
        $this->authorize('update', $booking);

        $this->attendance->confirm($booking);

        return new BookingApiResource(
            $booking->fresh()->load(['service:id,name', 'relative', 'booker:id,name,phone'])
        );
    }

    /** Revoke a mistaken attendance stamp. No-op if not attended. */
    public function revoke(Booking $booking): BookingApiResource
    {
        $this->authorize('update', $booking);

        $this->attendance->revoke($booking);

        return new BookingApiResource(
            $booking->fresh()->load(['service:id,name', 'relative', 'booker:id,name,phone'])
        );
    }
}
