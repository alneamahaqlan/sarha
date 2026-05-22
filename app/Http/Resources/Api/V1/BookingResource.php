<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'reference_code' => $this->reference_code,
            'clinic_id'      => $this->clinic_id,
            'user_id'        => $this->user_id,
            'service_id'     => $this->service_id,
            'customer_name'  => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'notes'          => $this->notes,
            'clinic_notes'   => $this->clinic_notes,
            'status'         => $this->status,
            'appointment_at' => $this->appointment_at?->toIso8601String(),
            'source'         => $this->source,
            'clinic'         => $this->whenLoaded('clinic', fn() => [
                'id'   => $this->clinic->id,
                'name' => $this->clinic->name,
            ]),
            'service'        => $this->whenLoaded('service', fn() => $this->service ? [
                'id'   => $this->service->id,
                'name' => $this->service->name,
            ] : null),
            'is_trashed'     => $this->trashed(),
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
            'deleted_at'     => $this->deleted_at?->toIso8601String(),
        ];
    }
}
