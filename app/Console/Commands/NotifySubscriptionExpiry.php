<?php

namespace App\Console\Commands;

use App\Services\SubscriptionLifecycleService;
use Illuminate\Console\Command;

/**
 * Daily lifecycle pass for subscriptions:
 *   1. Warn clinics whose subscription expires in N / 3 / 1 days
 *   2. Notify clinics whose subscription expired today (grace started)
 *   3. Auto-suspend clinics whose grace window has lapsed
 *
 * All three steps run via SubscriptionLifecycleService so the
 * notification routing + de-duplication lives in one place.
 *
 * Scheduled in routes/console.php at 02:00 KSA. Idempotent — safe to
 * re-run on demand (e.g. after fixing a clock skew).
 */
class NotifySubscriptionExpiry extends Command
{
    protected $signature = 'saerha:notify-subscription-expiry {--dry-run : Show counts without dispatching or suspending}';
    protected $description = 'Send expiry warnings, mark-expired notifications, and auto-suspend clinics past their grace period.';

    public function handle(SubscriptionLifecycleService $lifecycle): int
    {
        if ($this->option('dry-run')) {
            $this->warn('DRY-RUN: no notifications dispatched, no clinics suspended.');
            return self::SUCCESS;
        }

        $warned    = $lifecycle->notifyExpiringSoon();
        $expired   = $lifecycle->notifyJustExpired();
        $suspended = $lifecycle->suspendPastGracePeriod();

        $this->info("Expiring-soon warnings: {$warned}");
        $this->info("Just-expired notifications: {$expired}");
        $this->info("Auto-suspended (past grace): {$suspended}");

        return self::SUCCESS;
    }
}
