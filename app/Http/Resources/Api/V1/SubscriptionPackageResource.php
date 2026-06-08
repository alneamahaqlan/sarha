<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wire shape for a subscription package. Includes `clinics_count` so
 * the admin UI can warn before deleting / deactivating a package that
 * still has clinics on it.
 */
class SubscriptionPackageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                          => $this->id,
            'slug'                        => $this->slug,
            'name_ar'                     => $this->name_ar,
            'name_en'                     => $this->name_en,
            'tagline_ar'                  => $this->tagline_ar,
            'tagline_en'                  => $this->tagline_en,
            'color_token'                 => $this->color_token,
            'monthly_price'               => (float) $this->monthly_price,
            'sort_order'                  => (int) $this->sort_order,
            'is_active'                   => (bool) $this->is_active,
            // 12 features
            'services_limit'              => $this->services_limit,
            'articles_monthly_limit'      => $this->articles_monthly_limit,
            'ai_article_generation'       => (bool) $this->ai_article_generation,
            'featured_in_search'          => (bool) $this->featured_in_search,
            'ai_assistant_priority'       => (int) $this->ai_assistant_priority,
            'google_reviews_sync'         => (bool) $this->google_reviews_sync,
            'verified_badge'              => (bool) $this->verified_badge,
            'analytics_level'             => $this->analytics_level,
            'quote_replies_monthly_limit' => $this->quote_replies_monthly_limit,
            'banner_slots'                => (int) $this->banner_slots,
            'allow_offers_packages'       => (bool) $this->allow_offers_packages,
            'allow_doctors_before_after'  => (bool) $this->allow_doctors_before_after,
            'crm_enabled'                 => (bool) $this->crm_enabled,
            // Per-package "similar sections" config — always normalised (stored
            // values merged over defaults) so the admin UI gets a full matrix.
            'similar_config'              => $this->similarConfig(),
            // counters (so the UI can warn before destructive actions)
            'clinics_count'               => (int) ($this->clinics_count ?? 0),
            'created_at'                  => $this->created_at?->toIso8601String(),
            'updated_at'                  => $this->updated_at?->toIso8601String(),
        ];
    }
}
