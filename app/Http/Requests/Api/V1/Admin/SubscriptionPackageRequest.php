<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SubscriptionPackage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for create + update of a SubscriptionPackage.
 *
 * Slug uniqueness ignores the current row on update so an admin
 * editing the package without renaming doesn't trip on themselves.
 *
 * `null` is accepted for the four "limit" integers — that's how the
 * catalogue expresses "unlimited" (services, articles, replies,
 * with banner_slots being the exception since it's always concrete).
 */
class SubscriptionPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is already gated by auth:admin
    }

    public function rules(): array
    {
        $id = $this->route('subscription_package')?->id;

        return [
            'slug'        => ['required', 'string', 'max:32', 'alpha_dash', Rule::unique('subscription_packages', 'slug')->ignore($id)],
            'name_ar'     => 'required|string|max:120',
            'name_en'     => 'required|string|max:120',
            'tagline_ar'  => 'nullable|string|max:255',
            'tagline_en'  => 'nullable|string|max:255',
            'color_token' => 'required|string|in:gray,sage,gold,plum,info,warning',
            'monthly_price' => 'required|numeric|min:0|max:99999',
            'sort_order'  => 'required|integer|min:0|max:9999',
            'is_active'   => 'required|boolean',

            // 12 features
            'services_limit'              => 'nullable|integer|min:0|max:99999',
            'articles_monthly_limit'      => 'nullable|integer|min:0|max:99999',
            'ai_article_generation'       => 'required|boolean',
            'featured_in_search'          => 'required|boolean',
            'ai_assistant_priority'       => 'required|integer|min:0|max:2',
            'google_reviews_sync'         => 'required|boolean',
            'verified_badge'              => 'required|boolean',
            'analytics_level'             => 'required|in:' . SubscriptionPackage::ANALYTICS_BASIC . ',' . SubscriptionPackage::ANALYTICS_FULL,
            'quote_replies_monthly_limit' => 'nullable|integer|min:0|max:99999',
            'banner_slots'                => 'required|integer|min:0|max:20',
            'allow_offers_packages'       => 'required|boolean',
            'allow_doctors_before_after'  => 'required|boolean',
            'crm_enabled'                 => 'required|boolean',

            // Per-package config for the public "similar / related" strips.
            // Nullable → falls back to SubscriptionPackage::defaultSimilarConfig().
            'similar_config'                          => 'nullable|array',
            'similar_config.limit'                    => 'nullable|integer|min:1|max:24',
            'similar_config.sections'                 => 'nullable|array',
            'similar_config.sections.*.show'          => 'boolean',
            'similar_config.sections.*.match_city'    => 'boolean',
            'similar_config.sections.*.match_specialty' => 'boolean',
            'similar_config.sections.*.match_subclinic' => 'boolean',
        ];
    }
}
