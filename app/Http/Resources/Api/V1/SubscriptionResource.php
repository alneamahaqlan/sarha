<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray($request): array
    {
        $daysRemaining = null;
        if ($this->ends_at) {
            $diff = (int) now()->startOfDay()->diffInDays($this->ends_at->startOfDay(), false);
            $daysRemaining = $diff;
        }

        return [
            'id'                       => $this->id,
            'clinic_id'                => $this->clinic_id,
            'subscription_package_id'  => $this->subscription_package_id,
            'type'                     => $this->type,
            'billing_cycle'            => $this->billing_cycle,
            'bonus_months'             => (int) ($this->bonus_months ?? 0),
            'amount'                   => (float) $this->amount,
            'starts_at'                => $this->starts_at?->toIso8601String(),
            'ends_at'                  => $this->ends_at?->toIso8601String(),
            'days_remaining'           => $daysRemaining,
            'status'                   => $this->status,
            'moyasar_payment_id'       => $this->moyasar_payment_id,
            'notes'                    => $this->notes,
            'clinic'                   => $this->whenLoaded('clinic', fn() => $this->clinic ? [
                'id'   => $this->clinic->id,
                'name' => $this->clinic->name,
            ] : null),
            'package'                  => $this->whenLoaded('package', fn() => $this->package ? [
                'id'           => $this->package->id,
                'slug'         => $this->package->slug,
                'name_ar'      => $this->package->name_ar,
                'name_en'      => $this->package->name_en,
                'color_token'  => $this->package->color_token,
                'monthly_price'=> (float) $this->package->monthly_price,
            ] : null),
            'created_at'               => $this->created_at?->toIso8601String(),
            'updated_at'               => $this->updated_at?->toIso8601String(),
        ];
    }
}
