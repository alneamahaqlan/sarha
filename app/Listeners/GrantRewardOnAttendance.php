<?php

namespace App\Listeners;

use App\Events\BookingAttendanceConfirmed;
use App\Services\RewardService;
use Illuminate\Support\Facades\Log;

/**
 * Phase 1 hook into the attendance seam: mint the clinic's configured
 * cashback voucher the moment a patient's attendance is confirmed.
 * Wrapped so a reward failure never breaks the attendance confirmation.
 */
class GrantRewardOnAttendance
{
    public function __construct(private readonly RewardService $rewards) {}

    public function handle(BookingAttendanceConfirmed $event): void
    {
        try {
            $this->rewards->grantFromAttendance($event->booking);
        } catch (\Throwable $e) {
            Log::warning('GrantRewardOnAttendance failed', [
                'booking' => $event->booking->id,
                'err'     => $e->getMessage(),
            ]);
        }
    }
}
