<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\Subscription;
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
    public const PLAN_BASIC_PRICE   = 300;
    public const PLAN_PREMIUM_PRICE = 400;

    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * Approve a pending clinic: activates it, creates Subscription, logs audit,
     * fires clinicApproved notification.
     * Mirrors ClinicResource 'approve' action body exactly.
     */
    public function approve(Clinic $clinic): Clinic
    {
        return DB::transaction(function () use ($clinic) {
            $plan = $clinic->subscription_type ?? 'basic';

            $clinic->update([
                'status'                 => 'active',
                'subscription_type'      => $plan,
                'subscription_starts_at' => now(),
                'subscription_ends_at'   => now()->addDays(self::TRIAL_DAYS),
            ]);

            Subscription::create([
                'clinic_id' => $clinic->id,
                'type'      => $plan,
                'amount'    => $plan === 'premium' ? self::PLAN_PREMIUM_PRICE : self::PLAN_BASIC_PRICE,
                'starts_at' => now(),
                'ends_at'   => now()->addDays(self::TRIAL_DAYS),
                'status'    => 'active',
            ]);

            AuditLogService::log('clinic.approved', $clinic);
            $this->notifications->clinicApproved($clinic);

            return $clinic->fresh();
        });
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
