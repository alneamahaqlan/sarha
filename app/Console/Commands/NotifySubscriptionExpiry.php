<?php

namespace App\Console\Commands;

use App\Models\Clinic;
use App\Models\PlatformNotification;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class NotifySubscriptionExpiry extends Command
{
    protected $signature = 'saerha:notify-subscription-expiry';
    protected $description = 'Notify clinics whose subscription is expiring in 10/3/1 days, and admins of expired ones.';

    public function handle(NotificationService $notifications): int
    {
        $sent = 0;

        // 10 / 3 / 1 day warnings (skip if already sent today)
        foreach ([10, 3, 1] as $daysLeft) {
            $target = now()->addDays($daysLeft)->startOfDay();
            $clinics = Clinic::where('status', 'active')
                ->whereDate('subscription_ends_at', $target)
                ->get();

            foreach ($clinics as $clinic) {
                $alreadySent = PlatformNotification::forRecipient($clinic)
                    ->where('type', 'subscription_expiring')
                    ->whereDate('created_at', today())
                    ->where('data->days_left', $daysLeft)
                    ->exists();

                if (! $alreadySent) {
                    $notifications->subscriptionExpiringSoon($clinic, $daysLeft);
                    $sent++;
                }
            }
        }

        // Expired (ran out yesterday or earlier, still status=active)
        $expired = Clinic::where('status', 'active')
            ->whereNotNull('subscription_ends_at')
            ->where('subscription_ends_at', '<', now()->startOfDay())
            ->get();

        foreach ($expired as $clinic) {
            $alreadySent = PlatformNotification::forRecipient($clinic)
                ->where('type', 'subscription_expired')
                ->exists();

            if (! $alreadySent) {
                $notifications->subscriptionExpired($clinic);
                $sent++;
            }
        }

        $this->info("Sent {$sent} notifications.");
        return self::SUCCESS;
    }
}
