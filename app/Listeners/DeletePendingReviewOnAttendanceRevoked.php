<?php

namespace App\Listeners;

use App\Events\BookingAttendanceRevoked;
use App\Services\VerifiedReviewService;
use Illuminate\Support\Facades\Log;

/**
 * Symmetric counterpart of CreateReviewEligibilityOnAttendance: when a
 * reception mis-click is undone, drop the still-pending review for that
 * booking so we never invite a patient to review a visit that didn't
 * happen. An already-submitted (published) review is kept. Wrapped so it
 * never breaks the attendance revocation.
 */
class DeletePendingReviewOnAttendanceRevoked
{
    public function __construct(private readonly VerifiedReviewService $reviews) {}

    public function handle(BookingAttendanceRevoked $event): void
    {
        try {
            $this->reviews->deletePendingFor($event->booking);
        } catch (\Throwable $e) {
            Log::warning('DeletePendingReviewOnAttendanceRevoked failed', [
                'booking' => $event->booking->id,
                'err'     => $e->getMessage(),
            ]);
        }
    }
}
