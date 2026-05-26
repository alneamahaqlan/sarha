<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\SalesLead;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Single source of truth for SalesLead conversion.
 *
 * Extracted verbatim from SalesLeadResource::convertLead() so that Filament
 * and the API path execute the EXACT same DB::transaction + side effects.
 * No business-logic change.
 */
class SalesLeadService
{
    public const PLAN_BASIC_PRICE   = 300;
    public const PLAN_PREMIUM_PRICE = 400;
    public const TRIAL_DAYS         = 90;

    public function __construct(private readonly NotificationService $notifications) {}

    public function convertLead(SalesLead $lead, string $plan): Clinic
    {
        // Validate plan up front (was implicit via UI in Filament).
        if (! in_array($plan, ['basic', 'premium'], true)) {
            throw new \InvalidArgumentException("Unsupported plan: {$plan}");
        }

        $price = $plan === 'premium' ? self::PLAN_PREMIUM_PRICE : self::PLAN_BASIC_PRICE;
        $days  = self::TRIAL_DAYS;

        return DB::transaction(function () use ($lead, $plan, $price, $days) {
            $clinic = Clinic::create([
                'name'                   => $lead->clinic_name,
                'slug'                   => Str::slug($lead->clinic_name) . '-' . Str::random(4),
                'phone'                  => $lead->phone,
                'email'                  => $lead->email ?: ('lead-' . $lead->id . '@saerha.sa'),
                'license_number'         => $lead->license_number,
                'password'               => bcrypt(Str::random(12)),
                'city_id'                => $lead->city_id,
                'district'               => $lead->district,
                'address'                => $lead->address,
                'status'                 => 'active',
                'subscription_type'      => $plan,
                'subscription_starts_at' => now(),
                'subscription_ends_at'   => now()->addDays($days),
            ]);

            Subscription::create([
                'clinic_id' => $clinic->id,
                'type'      => $plan,
                'amount'    => $price,
                'starts_at' => now(),
                'ends_at'   => now()->addDays($days),
                'status'    => 'active',
            ]);

            $lead->update(['status' => 'converted']);

            AuditLogService::log(
                action: 'sales_lead.converted',
                model: $clinic,
                newValues: ['lead_id' => $lead->id, 'plan' => $plan, 'amount' => $price],
            );

            $this->notifications->leadConverted($lead, $clinic);
            $this->notifications->clinicApproved($clinic);

            return $clinic;
        });
    }
}
