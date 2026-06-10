<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class LandingPageBlockResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'landing_page_id' => $this->landing_page_id,
            'type'            => $this->type,
            'sort_order'      => (int) $this->sort_order,
            'is_visible'      => (bool) $this->is_visible,
            'config'          => $this->config ?? [],
            'block_version'   => (int) $this->block_version,
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}
