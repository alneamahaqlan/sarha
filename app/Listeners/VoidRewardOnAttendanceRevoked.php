<?php

namespace App\Listeners;

use App\Events\BookingAttendanceRevoked;
use App\Services\RewardService;
use Illuminate\Support\Facades\Log;

/**
 * Symmetric counterpart of GrantRewardOnAttendance: when a reception
 * mis-click is undone, void any still-active voucher that booking minted
 * (used vouchers are left untouched). Wrapped so it never breaks the
 * attendance revocation.
 */
class VoidRewardOnAttendanceRevoked
{
    public function __construct(private readonly RewardService $rewards) {}

    public function handle(BookingAttendanceRevoked $event): void
    {
        try {
            $this->rewards->voidFromRevokedAttendance($event->booking);
        } catch (\Throwable $e) {
            Log::warning('VoidRewardOnAttendanceRevoked failed', [
                'booking' => $event->booking->id,
                'err'     => $e->getMessage(),
            ]);
        }
    }
}
