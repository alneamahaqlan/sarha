<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class SubClinicResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'clinic_id'      => $this->clinic_id,
            'category_id'    => $this->category_id,
            'name'           => $this->name,
            'name_en'        => $this->name_en,
            'description'    => $this->description,
            'is_active'      => (bool) $this->is_active,
            'sort_order'     => (int) $this->sort_order,
            'services_count' => $this->whenCounted('allServices'),
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
