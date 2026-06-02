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
        // Per-package config for the public "similar / related" strips.
        'similar_config',
    ];

    /** The five "similar" section types a package can configure. */
    public const SIMILAR_SECTIONS = ['complex', 'service', 'offer', 'subClinic', 'doctor'];

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
            'similar_config'               => 'array',
        ];
    }

    /**
     * Default "similar sections" config — applied when a package has none
     * stored (NULL column) or for keys an older config omits. Conservative:
     * everything on, match by city + specialty, 6 cards. The doctor section
     * adds `match_subclinic` (match the doctor's sub-clinic category too).
     */
    public static function defaultSimilarConfig(): array
    {
        $section = ['show' => true, 'match_city' => true, 'match_specialty' => true];

        $sections = [];
        foreach (self::SIMILAR_SECTIONS as $key) {
            $sections[$key] = $key === 'doctor'
                ? $section + ['match_subclinic' => true]
                : $section;
        }

        return ['limit' => 6, 'sections' => $sections];
    }

    /**
     * Stored config merged over the defaults so callers always get a full,
     * well-typed structure regardless of what (if anything) was saved.
     */
    public function similarConfig(): array
    {
        $defaults = self::defaultSimilarConfig();
        $stored   = is_array($this->similar_config) ? $this->similar_config : [];

        $limit = isset($stored['limit']) ? max(1, (int) $stored['limit']) : $defaults['limit'];

        $sections = [];
        foreach (self::SIMILAR_SECTIONS as $key) {
            $sections[$key] = array_merge($defaults['sections'][$key], $stored['sections'][$key] ?? []);
        }

        return ['limit' => $limit, 'sections' => $sections];
    }

    /** Normalised config for one section type (falls back to defaults). */
    public function similarSection(string $type): array
    {
        return $this->similarConfig()['sections'][$type]
            ?? self::defaultSimilarConfig()['sections']['complex'];
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
