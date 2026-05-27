<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class ServiceCategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'name_en'        => $this->name_en,
            'slug'           => $this->slug,
            'emoji'          => $this->emoji,
            'icon'           => $this->icon,
            'description'    => $this->description,
            'is_active'      => (bool) $this->is_active,
            'sort_order'     => (int) $this->sort_order,
            // Surfaces "how many services are classified under me?" so the
            // admin index table can show a count column and the delete guard
            // has a quick conditional check on the client.
            'services_count' => $this->when(isset($this->services_count), (int) $this->services_count),
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
