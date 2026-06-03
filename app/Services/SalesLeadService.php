<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\SalesLead;
use App\Models\SubscriptionPackage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Single source of truth for SalesLead conversion.
 *
 * Conversion creates the Clinic shell then delegates ALL pricing to
 * SubscriptionService::activate() — the one place that reads the
 * subscription_packages catalogue. The package supplies the features +
 * the default price; the admin may pass a per-subscription manual
 * `amount` for clinics on a negotiated rate.
 */
class SalesLeadService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /**
     * @param SubscriptionPackage $package Tier picked by the admin — the
     *        source of truth for features + default price.
     * @param string $cycle  Subscription::CYCLE_QUARTERLY | CYCLE_ANNUAL.
     * @param ?float $amount  Manual per-subscription price; null = use the
     *        package-derived default (monthly_price × cycle months).
     */
    public function convertLead(SalesLead $lead, SubscriptionPackage $package, string $cycle, ?float $amount = null): Clinic
    {
        return DB::transaction(function () use ($lead, $package, $cycle, $amount) {
            $clinic = Clinic::create([
                'name'           => $lead->clinic_name,
                'slug'           => Str::slug($lead->clinic_name) . '-' . Str::lower(Str::random(4)),
                'phone'          => $lead->phone,
                'email'          => $lead->email ?: ('lead-' . $lead->id . '@saerha.sa'),
                'license_number' => $lead->license_number,
                'password'       => bcrypt(Str::random(16)),
                'city_id'        => $lead->city_id,
                'district'       => $lead->district,
                'address'        => $lead->address,
                'status'         => 'active',
            ]);

            // Single pricing path: activate() sets subscription_package_id,
            // the feature flags, the amount (default or manual override) and
            // syncs the clinic's denormalised subscription_* columns.
            $subscription = $this->subscriptions->activate(
                clinic: $clinic,
                package: $package,
                cycle: $cycle,
                bonusMonths: 0,
                adminId: auth('admin')->id(),
                notes: __('admin.subscriptions.lead_conversion_note', ['lead' => $lead->id]),
                amountOverride: $amount,
            );

            $lead->update(['status' => 'converted']);

            AuditLogService::log(
                action: 'sales_lead.converted',
                model: $clinic,
                newValues: [
                    'lead_id' => $lead->id,
                    'package' => $package->slug,
                    'cycle'   => $cycle,
                    'amount'  => $subscription->amount,
                ],
            );

            $this->notifications->leadConverted($lead, $clinic);
            $this->notifications->clinicApproved($clinic);

            return $clinic;
        });
    }
}
