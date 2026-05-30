<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A subscription tier the platform offers to clinics. Three rows are
 * seeded by default (free / standard / premium) but the catalogue is
 * fully editable from the super-admin panel.
 *
 * The 12 feature columns each map to a single gate or quota enforced
 * by FeatureGate::*. Nullable integer limits mean "unlimited"; bool
 * features default to false so adding a new tier is conservative.
 *
 * The `color_token` field drives the React badge palette — kept as a
 * short string ('gray' | 'sage' | 'gold') so the JS layer doesn't
 * have to know about the package's specific hex.
 */
class SubscriptionPackage extends Model
{
    use HasFactory;

    /** Slugs the legacy `clinics.subscription_type` column can carry. */
    public const SLUG_FREE     = 'free';
    public const SLUG_STANDARD = 'standard';
    public const SLUG_PREMIUM  = 'premium';

    /** Analytics levels — kept here so the React UI + FeatureGate match. */
    public const ANALYTICS_BASIC = 'basic';
    public const ANALYTICS_FULL  = 'full';

    protected $fillable = [
        'slug', 'name_ar', 'name_en', 'tagline_ar', 'tagline_en',
        'color_token', 'monthly_price', 'sort_order', 'is_active',
        // 12 features
        'services_limit', 'articles_monthly_limit', 'ai_article_generation',
        'featured_in_search', 'ai_assistant_priority', 'google_reviews_sync',
        'verified_badge', 'analytics_level', 'quote_replies_monthly_limit',
        'banner_slots', 'allow_offers_packages', 'allow_doctors_before_after',
    ];

    protected function casts(): array
    {
        return [
            'monthly_price'                => 'decimal:2',
            'sort_order'                   => 'integer',
            'is_active'                    => 'boolean',
            'services_limit'               => 'integer',
            'articles_monthly_limit'       => 'integer',
            'ai_article_generation'        => 'boolean',
            'featured_in_search'           => 'boolean',
            'ai_assistant_priority'        => 'integer',
            'google_reviews_sync'          => 'boolean',
            'verified_badge'               => 'boolean',
            'quote_replies_monthly_limit'  => 'integer',
            'banner_slots'                 => 'integer',
            'allow_offers_packages'        => 'boolean',
            'allow_doctors_before_after'   => 'boolean',
        ];
    }

    public function clinics()
    {
        return $this->hasMany(Clinic::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /** Localised name for the current request locale. */
    public function displayName(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }

    /** Convenience accessor for "is this the free tier?" */
    public function isFree(): bool
    {
        return (float) $this->monthly_price === 0.0;
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('monthly_price');
    }
}
