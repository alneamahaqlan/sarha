<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class PriceQuoteRequestResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'clinic_id'      => $this->clinic_id,
            'user_id'        => $this->user_id,
            'customer_name'  => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'service_name'   => $this->service_name,
            'description'    => $this->description,
            'status'         => $this->status,
            'clinic_reply'   => $this->clinic_reply,
            'clinic'         => $this->whenLoaded('clinic', fn() => $this->clinic ? [
                'id'   => $this->clinic->id,
                'name' => $this->clinic->name,
            ] : null),
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
