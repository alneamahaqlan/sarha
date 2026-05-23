<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class CityResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'name_en'       => $this->name_en,
            'is_active'     => (bool) $this->is_active,
            'sort_order'    => (int) $this->sort_order,
            'clinics_count' => $this->when(isset($this->clinics_count), (int) $this->clinics_count),
            'created_at'    => $this->created_at?->toIso8601String(),
            'updated_at'    => $this->updated_at?->toIso8601String(),
        ];
    }
}
