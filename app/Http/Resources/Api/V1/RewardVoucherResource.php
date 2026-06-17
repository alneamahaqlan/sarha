<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A reward voucher as the CLINIC sees it (its own issued vouchers).
 * The clinic legitimately sees the holder's phone/name (its customer);
 * the customer-facing account view uses its own Blade shape.
 */
class RewardVoucherResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'code'           => $this->code,
            'type'           => $this->type,
            'status'         => $this->status,
            'is_expired'     => $this->isExpired(),
            'source'         => $this->source,
            'phone'          => $this->phone,
            'customer_name'  => $this->whenLoaded('customer', fn () => $this->customer?->name),
            'offer'          => $this->whenLoaded('offer', fn () => $this->offer ? [
                'id'    => $this->offer->id,
                'title' => $this->offer->title,
            ] : null),
            'service'        => $this->whenLoaded('service', fn () => $this->service ? [
                'id'   => $this->service->id,
                'name' => $this->service->name,
            ] : null),
            'discount_type'  => $this->discount_type,
            'discount_value' => $this->discount_value !== null ? (float) $this->discount_value : null,
            'expires_at'     => $this->expires_at?->toIso8601String(),
            'used_at'        => $this->used_at?->toIso8601String(),
            'granted_by_name'=> $this->granted_by_name,
            'origin_reference'   => $this->whenLoaded('originBooking', fn () => $this->originBooking?->reference_code),
            'applied_reference'  => $this->whenLoaded('appliedBooking', fn () => $this->appliedBooking?->reference_code),
            'redeemed_reference' => $this->whenLoaded('redeemedBooking', fn () => $this->redeemedBooking?->reference_code),
            'created_at'     => $this->created_at?->toIso8601String(),
        ];
    }
}
