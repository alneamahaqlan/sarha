<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class HomepageSectionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                      => $this->id,
            'key'                     => $this->key,
            'type'                    => $this->type,
            'title_ar'                => $this->title_ar,
            'title_en'                => $this->title_en,
            'is_active'               => (bool) $this->is_active,
            'sort_order'              => (int) $this->sort_order,
            'item_limit'              => $this->item_limit !== null ? (int) $this->item_limit : null,
            'banner_interval_seconds' => $this->banner_interval_seconds !== null ? (int) $this->banner_interval_seconds : null,
            'show_on_mobile'          => (bool) $this->show_on_mobile,
            'show_on_desktop'         => (bool) $this->show_on_desktop,
            'starts_at'               => $this->starts_at?->toIso8601String(),
            'ends_at'                 => $this->ends_at?->toIso8601String(),
            'config'                  => $this->config,
            'banner_slides'           => HomepageBannerSlideResource::collection($this->whenLoaded('bannerSlides')),
            'banner_slides_count'     => $this->whenCounted('bannerSlides'),
            'created_at'              => $this->created_at?->toIso8601String(),
            'updated_at'              => $this->updated_at?->toIso8601String(),
        ];
    }
}
