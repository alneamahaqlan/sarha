<?php

namespace App\Services;

use App\Enums\NotificationEvent;
use App\Models\Clinic;
use App\Models\PlatformNotification;
use App\Models\Subscription;
use App\Models\SubscriptionPackage;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Log;

/**
 * Daily lifecycle pass — runs from the scheduler:
 *
 *   1. notifyExpiringSoon()       T-10 / T-3 / T-1 → bell + push to clinic
 *   2. notifyJustExpired()        T+0 → bell + push (grace countdown starts)
 *   3. suspendPastGracePeriod()   T+grace → clinic.status = suspended
 *
 * Idempotent: each pass de-dupes via PlatformNotification so re-running
 * the command the same day doesn't spam.
 *
 * Everything reads via NotificationDispatcher so the bell, broadcast,
 * and Web Push fan-out for free — no parallel call sites.
 */
class SubscriptionLifecycleService
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    // ─────────────────────────── public API ───────────────────────────

    /**
     * Fire SUBSCRIPTION_EXPIRING_SOON at the configured warning offsets.
     * Default offsets are 10/3/1 days; admin can shrink via the
     * `subscription_reminder_days` setting (treated as the *farthest*
     * warning — closer ones 3 + 1 always fire as long as they're > 0).
     */
    public function notifyExpiringSoon(): int
    {
        $farthest = max(1, (int) SystemSetting::get('subscription_reminder_days', 10));
        // 10/3/1 (or whatever farthest the admin picked + 3 + 1, dedup).
        $offsets = collect([$farthest, 3, 1])->unique()->sort()->values()->all();

        $sent = 0;
        foreach ($offsets as $daysLeft) {
            $target = now()->addDays($daysLeft)->toDateString();
            $clinics = Clinic::where('status', 'active')
                ->whereNotNull('subscription_ends_at')
                ->whereDate('subscription_ends_at', $target)
                ->get();

            foreach ($clinics as $clinic) {
                if ($this->alreadySent($clinic, NotificationEvent::SUBSCRIPTION_EXPIRING_SOON, ['days_left' => $daysLeft])) {
                    continue;
                }
                if ($this->dispatchExpiring($clinic, $daysLeft)) {
                    $sent++;
                }
            }
        }
        return $sent;
    }

    /**
     * Fire SUBSCRIPTION_EXPIRED the day the subscription ran out. We
     * notify once per (clinic, subscription_ends_at) so a clinic that
     * renews + expires again later in the year still gets re-notified.
     */
    public function notifyJustExpired(): int
    {
        $today = now()->toDateString();
        $clinics = Clinic::where('status', 'active')
            ->whereNotNull('subscription_ends_at')
            ->whereDate('subscription_ends_at', '<=', $today)
            ->get();

        $sent = 0;
        foreach ($clinics as $clinic) {
            $endsAt = $clinic->subscription_ends_at?->toDateString();
            if ($endsAt === null) continue;

            if ($this->alreadySent($clinic, NotificationEvent::SUBSCRIPTION_EXPIRED, ['ends_at' => $endsAt])) {
                continue;
            }
            if ($this->dispatchExpired($clinic)) {
                $sent++;
            }
        }
        return $sent;
    }

    /**
     * Auto-suspend clinics whose grace window ran out. The clinic's
     * `status` flips to `suspended`, which is the same flag the admin
     * uses for manual suspensions — so publiclyVisible() filters them
     * out and the existing UX (banners, sign-in gate) just works.
     *
     * Returns the number of clinics actually suspended.
     */
    public function suspendPastGracePeriod(): int
    {
        if (! (bool) SystemSetting::get('subscription_auto_suspend_enabled', true)) {
            return 0;
        }
        $graceDays = max(0, (int) SystemSetting::get('subscription_grace_period_days', 7));

        $cutoff = now()->subDays($graceDays)->startOfDay();

        $clinics = Clinic::where('status', 'active')
            ->whereNotNull('subscription_ends_at')
            ->where('subscription_ends_at', '<', $cutoff)
            ->get();

        $suspended = 0;
        foreach ($clinics as $clinic) {
            try {
                $clinic->update(['status' => 'suspended']);
                $suspended++;
                // No extra notification here — the EXPIRED event already
                // warned them. A "you got suspended" follow-up would just
                // duplicate the same bell row and add noise.
            } catch (\Throwable $e) {
                Log::warning('Auto-suspend failed', [
                    'clinic_id' => $clinic->id,
                    'err' => $e->getMessage(),
                ]);
            }
        }
        return $suspended;
    }

    // ─────────────────────────── dispatchers ──────────────────────────

    /**
     * One-shot dispatch used by SubscriptionService::activate() so the
     * clinic owner gets a receipt as soon as the admin clicks "save".
     */
    public function dispatchActivated(Clinic $clinic, Subscription $subscription): void
    {
        $package = $subscription->package ?? SubscriptionPackage::find($subscription->subscription_package_id);
        if (! $package) return;

        $this->dispatcher->dispatch(NotificationEvent::SUBSCRIPTION_ACTIVATED, $clinic, [
            'package'   => $package->name_ar,
            'ends_at'   => $subscription->ends_at?->toDateString(),
            'sub_id'    => $subscription->id,
        ]);
    }

    /**
     * Fired from SubscriptionService::cancel(). The clinic might be
     * about to lose access if this was their current sub — the
     * notification points them at the subscription page where the
     * "contact admin to reactivate" CTA lives.
     */
    public function dispatchCancelled(Clinic $clinic, Subscription $subscription): void
    {
        $package = $subscription->package ?? SubscriptionPackage::find($subscription->subscription_package_id);

        $this->dispatcher->dispatch(NotificationEvent::SUBSCRIPTION_CANCELLED, $clinic, [
            'package' => $package?->name_ar ?? $subscription->type,
            'sub_id'  => $subscription->id,
        ]);
    }

    // ─────────────────────────── internals ────────────────────────────

    private function dispatchExpiring(Clinic $clinic, int $daysLeft): bool
    {
        $package = $clinic->package;
        $note = $this->dispatcher->dispatch(NotificationEvent::SUBSCRIPTION_EXPIRING_SOON, $clinic, [
            'package'    => $package?->name_ar ?? __('admin.clinics.no_package'),
            'days'       => $daysLeft,
            'days_left'  => $daysLeft,
            'ends_at'    => $clinic->subscription_ends_at?->toDateString(),
        ]);
        return $note !== null;
    }

    private function dispatchExpired(Clinic $clinic): bool
    {
        $package = $clinic->package;
        $grace = (int) SystemSetting::get('subscription_grace_period_days', 7);

        $note = $this->dispatcher->dispatch(NotificationEvent::SUBSCRIPTION_EXPIRED, $clinic, [
            'package' => $package?->name_ar ?? __('admin.clinics.no_package'),
            'grace'   => $grace,
            'ends_at' => $clinic->subscription_ends_at?->toDateString(),
        ]);
        return $note !== null;
    }

    /**
     * De-dupe guard: returns true if a notification of the given event
     * was already created for this clinic with overlapping payload keys.
     * We compare every key passed in `$dataFilter` against the JSON
     * `data` column — partial match is enough so we don't re-fire when
     * the daily cron runs twice.
     */
    private function alreadySent(Clinic $clinic, NotificationEvent $event, array $dataFilter): bool
    {
        $q = PlatformNotification::forRecipient($clinic)
            ->where('event_type', $event->value);

        foreach ($dataFilter as $k => $v) {
            $q->where("data->{$k}", $v);
        }
        return $q->exists();
    }
}
