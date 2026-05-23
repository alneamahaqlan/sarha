<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                 => $this->id,
            'clinic_id'          => $this->clinic_id,
            'type'               => $this->type,
            'amount'             => (float) $this->amount,
            'starts_at'          => $this->starts_at?->toIso8601String(),
            'ends_at'            => $this->ends_at?->toIso8601String(),
            'status'             => $this->status,
            'moyasar_payment_id' => $this->moyasar_payment_id,
            'notes'              => $this->notes,
            'clinic'             => $this->whenLoaded('clinic', fn() => $this->clinic ? [
                'id'   => $this->clinic->id,
                'name' => $this->clinic->name,
            ] : null),
            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),
        ];
    }
}
