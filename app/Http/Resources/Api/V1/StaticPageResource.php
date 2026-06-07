<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class StaticPageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                  => $this->id,
            'slug'                => $this->slug,
            'title_ar'            => $this->title_ar,
            'title_en'            => $this->title_en,
            'body_ar'             => $this->body_ar,
            'body_en'             => $this->body_en,
            'meta_description_ar' => $this->meta_description_ar,
            'meta_description_en' => $this->meta_description_en,
            'is_active'           => (bool) $this->is_active,
            'is_system'           => (bool) $this->is_system,
            'published_at'        => $this->published_at?->toIso8601String(),
            'sort_order'          => (int) $this->sort_order,
            'created_at'          => $this->created_at?->toIso8601String(),
            'updated_at'          => $this->updated_at?->toIso8601String(),
        ];
    }
}
