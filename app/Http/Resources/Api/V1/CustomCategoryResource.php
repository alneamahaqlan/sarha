<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomCategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'clinic_id'      => $this->clinic_id,
            'name'           => $this->name,
            'emoji'          => $this->emoji,
            'is_active'      => (bool) $this->is_active,
            'sort_order'     => (int) $this->sort_order,
            'services_count' => $this->when(isset($this->services_count), (int) $this->services_count),
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
