<?php

namespace App\Console\Commands;

use App\Enums\NotificationEvent;
use App\Models\RewardVoucher;
use App\Services\NotificationDispatcher;
use App\Services\SmsService;
use Illuminate\Console\Command;

/**
 * Win-back nudge: reminds holders of an IDLE, still-unused reward to come
 * back and use it. Deliberately distinct from the expiry reminder —
 *
 *   - expiry reminder: fires once when a voucher is about to lapse.
 *   - this: fires for vouchers that are NOT near expiry but have been
 *     sitting unused for a while, on a cooldown so we never nag.
 *
 * Eligibility: active (unused), not expiring within --min-days-to-expiry
 * (leave those to the expiry reminder), granted at least --idle-days ago,
 * and not reactivation-reminded within --cooldown-days. Idempotent +
 * cooldown via reactivation_reminded_at. Scheduled weekly.
 */
class DispatchRewardReactivationReminders extends Command
{
    protected $signature = 'saerha:dispatch-reward-reactivation-reminders
        {--idle-days=14 : Only vouchers granted at least this long ago}
        {--cooldown-days=30 : Don\'t re-nudge within this many days}
        {--min-days-to-expiry=7 : Skip vouchers expiring within this many days (left to the expiry reminder)}
        {--dry-run}';
    protected $description = 'Nudge holders of idle, still-unused reward vouchers to come back and use them.';

    public function handle(NotificationDispatcher $dispatcher, SmsService $sms): int
    {
        $idleDays  = max(1, (int) $this->option('idle-days'));
        $cooldown  = max(1, (int) $this->option('cooldown-days'));
        $minExpiry = max(0, (int) $this->option('min-days-to-expiry'));

        $due = RewardVoucher::query()
            ->where('status', RewardVoucher::STATUS_ACTIVE)
            ->where('created_at', '<=', now()->subDays($idleDays))
            // Not near expiry (or no expiry) — the expiry reminder owns those.
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()->addDays($minExpiry)))
            // Cooldown: never nudged, or last nudge older than the cooldown.
            ->where(fn ($q) => $q->whereNull('reactivation_reminded_at')->orWhere('reactivation_reminded_at', '<', now()->subDays($cooldown)))
            ->with(['clinic:id,name', 'platformCustomer.user'])
            ->limit(500)
            ->get();

        if ($this->option('dry-run')) {
            $this->warn("DRY-RUN: {$due->count()} idle voucher(s) eligible — none nudged.");
            return self::SUCCESS;
        }

        $sent = 0;
        foreach ($due as $voucher) {
            $user = $voucher->platformCustomer?->user;
            if ($user) {
                $notification = $dispatcher->dispatch(NotificationEvent::REWARD_AVAILABLE, $user, [
                    'clinic' => $voucher->clinic?->name,
                    'code'   => $voucher->code,
                ]);
                if ($notification !== null) {
                    $sent++;
                }
            } elseif ($voucher->phone) {
                try {
                    $sms->send($voucher->phone, __('site.reward_available_sms', [
                        'clinic' => $voucher->clinic?->name ?? '',
                    ]));
                    $sent++;
                } catch (\Throwable $e) {
                    // logged by SmsService; stamp anyway to respect the cooldown
                }
            }

            $voucher->forceFill(['reactivation_reminded_at' => now()])->save();
        }

        $this->info("Reward reactivation reminders dispatched: {$sent} / {$due->count()} eligible.");

        return self::SUCCESS;
    }
}
