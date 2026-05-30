<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\Subscription;
use App\Models\SubscriptionPackage;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for Clinic state-transition actions.
 *
 * Extracted verbatim from the inline closures in Filament's ClinicResource so
 * BOTH the Filament panel and the API path execute the exact same code.
 * No business-logic change.
 */
class ClinicService
{
    public const TRIAL_DAYS         = 90;

    public function __construct(
        private readonly NotificationService $notifications,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /**
     * Approve a pending clinic. The new flow routes through
     * SubscriptionService::activate() so the clinic ends up with a
     * proper subscription_package_id FK + the 12 feature flags from
     * FeatureGate work from day one. Prior implementation seeded only
     * the legacy `subscription_type` string and left package_id null,
     * which silently demoted every newly-approved clinic to Free.
     *
     * Selects the package by matching legacy `subscription_type` to
     * slug (basic→standard fallback, premium→premium). Falls back to
     * the lowest-priced active package if nothing matches.
     */
    public function approve(Clinic $clinic): Clinic
    {
        return DB::transaction(function () use ($clinic) {
            $clinic->update(['status' => 'active']);

            $package = $this->resolveApprovalPackage($clinic);
            // 90-day trial = 1 quarterly cycle with 0 bonus months; the
            // service computes ends_at + amount from the package.
            $this->subscriptions->activate(
                clinic: $clinic,
                package: $package,
                cycle: Subscription::CYCLE_QUARTERLY,
                bonusMonths: 0,
                adminId: auth('admin')->id(),
                notes: __('admin.subscriptions.approval_trial_note', ['days' => self::TRIAL_DAYS]),
            );

            AuditLogService::log('clinic.approved', $clinic);
            $this->notifications->clinicApproved($clinic);

            return $clinic->fresh();
        });
    }

    /**
     * Pick which package an approval should activate. Legacy values
     * 'premium' / 'basic' map onto the seeded `premium` / `standard`
     * slugs; anything else (or null) falls back to the lowest-priced
     * active package — usually `free`.
     */
    private function resolveApprovalPackage(Clinic $clinic): SubscriptionPackage
    {
        $legacy = $clinic->subscription_type;
        $slug = match ($legacy) {
            'premium' => SubscriptionPackage::SLUG_PREMIUM,
            'basic'   => SubscriptionPackage::SLUG_STANDARD,
            default   => $legacy, // pass-through for already-correct slugs
        };

        $pkg = SubscriptionPackage::active()->where('slug', $slug)->first();
        if ($pkg) return $pkg;

        // Last-resort fallback — never leave approval without a package.
        return SubscriptionPackage::active()->ordered()->firstOrFail();
    }

    /**
     * Reject a pending clinic with a stored rejection reason.
     */
    public function reject(Clinic $clinic, string $reason): Clinic
    {
        $clinic->update([
            'status'           => 'rejected',
            'rejection_reason' => $reason,
        ]);

        AuditLogService::log('clinic.rejected', $clinic);
        $this->notifications->clinicRejected($clinic, $reason);

        return $clinic->fresh();
    }

    /**
     * Reactivate a suspended/rejected clinic.
     */
    public function activate(Clinic $clinic): Clinic
    {
        $clinic->update(['status' => 'active']);
        AuditLogService::log('clinic.activated', $clinic);

        return $clinic->fresh();
    }

    /**
     * Suspend an active clinic.
     */
    public function suspend(Clinic $clinic): Clinic
    {
        $clinic->update(['status' => 'suspended']);
        AuditLogService::log('clinic.suspended', $clinic);

        return $clinic->fresh();
    }

    /**
     * Extend the subscription_ends_at by N days (30 or 90 in current UI).
     * Mirrors Filament 'extend_30' and 'extend_90' exactly: if the current end
     * date is in the future, extend from it; otherwise extend from now().
     */
    public function extend(Clinic $clinic, int $days): Clinic
    {
        $base = $clinic->subscription_ends_at?->isFuture()
            ? $clinic->subscription_ends_at
            : now();

        $clinic->update(['subscription_ends_at' => $base->copy()->addDays($days)]);

        AuditLogService::log('clinic.extended', $clinic, newValues: ['days' => $days]);

        return $clinic->fresh();
    }
}
