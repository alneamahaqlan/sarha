<?php

namespace App\Console\Commands;

use App\Enums\NotificationEvent;
use App\Models\RewardVoucher;
use App\Services\NotificationDispatcher;
use App\Services\RewardService;
use Illuminate\Console\Command;

/**
 * Daily rewards housekeeping:
 *   1. expireDue() — flip any active voucher past its expiry to `expired`.
 *   2. Nudge holders whose active voucher expires within the window with
 *      REWARD_EXPIRING_SOON.
 *
 * Idempotent: only picks up rows with expiry_reminded_at IS NULL and
 * stamps it on processing, so the daily run never double-nudges.
 * Scheduled from bootstrap/app.php.
 */
class DispatchRewardExpiryReminders extends Command
{
    protected $signature = 'saerha:dispatch-reward-expiry-reminders {--days=3 : Remind when a voucher expires within this many days} {--dry-run}';
    protected $description = 'Expire lapsed reward vouchers and remind holders whose vouchers expire soon.';

    public function handle(NotificationDispatcher $dispatcher, RewardService $rewards): int
    {
        $expired = $rewards->expireDue();
        $this->info("Expired {$expired} lapsed voucher(s).");

        $withinDays = max(1, (int) $this->option('days'));
        $cutoff = now()->addDays($withinDays);

        $due = RewardVoucher::query()
            ->where('status', RewardVoucher::STATUS_ACTIVE)
            ->whereNull('expiry_reminded_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', $cutoff)
            ->with(['clinic:id,name', 'platformCustomer.user'])
            ->limit(500)
            ->get();

        if ($this->option('dry-run')) {
            $this->warn("DRY-RUN: {$due->count()} voucher(s) expiring within {$withinDays} day(s) — none notified.");
            return self::SUCCESS;
        }

        $sent = 0;
        foreach ($due as $voucher) {
            $user = $voucher->platformCustomer?->user;
            if ($user) {
                $days = max(1, (int) ceil(now()->floatDiffInDays($voucher->expires_at)));
                $notification = $dispatcher->dispatch(NotificationEvent::REWARD_EXPIRING_SOON, $user, [
                    'clinic' => $voucher->clinic?->name,
                    'code'   => $voucher->code,
                    'days'   => $days,
                ]);
                if ($notification !== null) {
                    $sent++;
                }
            }

            // Stamp regardless (orphans / account-less holders) so a poison
            // row is never reprocessed every night.
            $voucher->forceFill(['expiry_reminded_at' => now()])->save();
        }

        $this->info("Reward expiry reminders dispatched: {$sent} / {$due->count()} due.");

        return self::SUCCESS;
    }
}
