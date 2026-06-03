<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class HomepageBannerSlideResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                  => $this->id,
            'homepage_section_id' => $this->homepage_section_id,
            'image'               => $this->image,
            'image_url'           => $this->image ? Storage::url($this->image) : null,
            'link_url'            => $this->link_url,
            'is_active'           => (bool) $this->is_active,
            'sort_order'          => (int) $this->sort_order,
            'created_at'          => $this->created_at?->toIso8601String(),
            'updated_at'          => $this->updated_at?->toIso8601String(),
        ];
    }
}
