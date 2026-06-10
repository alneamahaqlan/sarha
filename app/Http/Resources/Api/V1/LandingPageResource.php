<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class LandingPageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                 => $this->id,
            'type'               => $this->type,
            'status'             => $this->status,
            'slug'               => $this->slug,
            'title_ar'           => $this->title_ar,
            'title_en'           => $this->title_en,
            'internal_name'      => $this->internal_name,

            'clinic_id'          => $this->clinic_id,
            'offer_id'           => $this->offer_id,
            'city_id'            => $this->city_id,
            'category_id'        => $this->category_id,

            'cover_image'        => $this->cover_image,
            'social_image'       => $this->social_image,

            'published_at'       => $this->published_at?->toIso8601String(),
            'starts_at'          => $this->starts_at?->toIso8601String(),
            'ends_at'            => $this->ends_at?->toIso8601String(),

            'cta_label_ar'       => $this->cta_label_ar,
            'cta_label_en'       => $this->cta_label_en,
            'cta_url'            => $this->cta_url,
            'cta_style'          => $this->cta_style,
            'whatsapp_phone'     => $this->whatsapp_phone,
            'call_phone'         => $this->call_phone,
            'whatsapp_enabled'   => (bool) $this->whatsapp_enabled,
            'call_enabled'       => (bool) $this->call_enabled,

            'seo_title_ar'       => $this->seo_title_ar,
            'seo_title_en'       => $this->seo_title_en,
            'seo_description_ar' => $this->seo_description_ar,
            'seo_description_en' => $this->seo_description_en,
            'seo_keywords'       => $this->seo_keywords,
            'canonical_url'      => $this->canonical_url,
            'meta_robots'        => $this->meta_robots,
            'in_sitemap'         => (bool) $this->in_sitemap,

            'og_title_ar'        => $this->og_title_ar,
            'og_title_en'        => $this->og_title_en,
            'og_description_ar'  => $this->og_description_ar,
            'og_description_en'  => $this->og_description_en,

            'schema_markup'      => $this->schema_markup,
            'schema_type'        => $this->schema_type,

            'total_views'        => (int) $this->total_views,
            'total_conversions'  => (int) $this->total_conversions,

            'comparison_clinic_ids' => $this->whenLoaded('clinics', fn () => $this->clinics->pluck('id')->all()),
            'blocks'             => LandingPageBlockResource::collection($this->whenLoaded('blocks')),

            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),
        ];
    }
}
