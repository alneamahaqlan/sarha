<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'clinic_id'     => $this->clinic_id,
            'clinic'        => $this->whenLoaded('clinic', fn () => [
                'id'   => $this->clinic->id,
                'name' => $this->clinic->name,
            ]),
            'title'            => $this->title,
            'slug'             => $this->slug,
            'meta_description' => $this->meta_description,
            'body'             => $this->body,
            'cover_image'      => $this->cover_image,
            'tags'          => $this->tags,
            'is_published'  => (bool) $this->is_published,
            'ai_generated'  => (bool) $this->ai_generated,
            'views_count'   => (int) $this->views_count,
            'published_at'  => $this->published_at?->toIso8601String(),
            'created_at'    => $this->created_at?->toIso8601String(),
            'updated_at'    => $this->updated_at?->toIso8601String(),
        ];
    }
}
