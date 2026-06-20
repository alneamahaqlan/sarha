<?php

namespace App\Listeners;

use App\Events\BookingAttendanceConfirmed;
use App\Services\VerifiedReviewService;
use Illuminate\Support\Facades\Log;

/**
 * Phase-2 hook into the attendance seam: the moment a patient's
 * attendance is confirmed, they become eligible to review that visit —
 * we create the pending review row. The post-visit invitation is sent
 * later by a scheduled command, NOT here. Wrapped so a failure never
 * breaks the attendance confirmation.
 */
class CreateReviewEligibilityOnAttendance
{
    public function __construct(private readonly VerifiedReviewService $reviews) {}

    public function handle(BookingAttendanceConfirmed $event): void
    {
        try {
            $this->reviews->createEligibility($event->booking);
        } catch (\Throwable $e) {
            Log::warning('CreateReviewEligibilityOnAttendance failed', [
                'booking' => $event->booking->id,
                'err'     => $e->getMessage(),
            ]);
        }
    }
}
