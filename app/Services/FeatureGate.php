<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Clinic;
use App\Models\PriceQuoteReply;
use App\Models\Service;
use App\Models\SubscriptionPackage;

/**
 * Single source of truth for "what is this clinic allowed to do?".
 *
 * Every caller — observers enforcing quotas, controllers shaping
 * payloads, public ranking code — flows through this service so a
 * package toggle in the admin UI takes effect everywhere without
 * grepping the codebase for hard-coded checks.
 *
 * Resolution rules:
 *   1. clinic.package (the FK we just wired) is the gospel
 *   2. if the clinic somehow has no package (legacy data drift),
 *      we synthesise a conservative "free" default so the user can
 *      still operate but with the tightest limits
 *
 * Caching:
 *   The gate caches the resolved package per clinic-id for the life
 *   of the request. Usage counters (articlesUsedThisMonth) are NOT
 *   cached because the same request might publish an article and
 *   then ask about the new total.
 */
class FeatureGate
{
    /** @var array<int, SubscriptionPackage> */
    private array $packageCache = [];

    /**
     * Conservative defaults applied when a clinic has no FK to a
     * package row. Mirrors the seeded Free tier so the experience
     * stays consistent.
     */
    private const FALLBACK = [
        'services_limit'              => 10,
        'articles_monthly_limit'      => 2,
        'ai_article_generation'       => false,
        'featured_in_search'          => false,
        'ai_assistant_priority'       => 0,
        'google_reviews_sync'         => false,
        'verified_badge'              => false,
        'analytics_level'             => SubscriptionPackage::ANALYTICS_BASIC,
        'quote_replies_monthly_limit' => 3,
        'banner_slots'                => 0,
        'allow_offers_packages'       => false,
        'allow_doctors_before_after'  => false,
    ];

    // ─────────────────────────── Limits (int|null = unlimited) ───────────────────────────

    public function servicesLimit(Clinic $clinic): ?int           { return $this->intOrNull($clinic, 'services_limit'); }
    public function articlesLimit(Clinic $clinic): ?int           { return $this->intOrNull($clinic, 'articles_monthly_limit'); }
    public function quoteRepliesLimit(Clinic $clinic): ?int       { return $this->intOrNull($clinic, 'quote_replies_monthly_limit'); }
    public function bannerSlots(Clinic $clinic): int              { return (int) $this->value($clinic, 'banner_slots'); }

    // ─────────────────────────── Booleans / tier values ───────────────────────────

    public function canGenerateAiArticle(Clinic $clinic): bool    { return (bool) $this->value($clinic, 'ai_article_generation'); }
    public function isFeaturedInSearch(Clinic $clinic): bool      { return (bool) $this->value($clinic, 'featured_in_search'); }
    public function hasGoogleReviewsSync(Clinic $clinic): bool    { return (bool) $this->value($clinic, 'google_reviews_sync'); }
    public function hasVerifiedBadge(Clinic $clinic): bool        { return (bool) $this->value($clinic, 'verified_badge'); }
    public function canPublishOffers(Clinic $clinic): bool        { return (bool) $this->value($clinic, 'allow_offers_packages'); }
    public function canShowDoctorsAndBeforeAfter(Clinic $clinic): bool { return (bool) $this->value($clinic, 'allow_doctors_before_after'); }

    public function aiAssistantPriority(Clinic $clinic): int      { return (int) $this->value($clinic, 'ai_assistant_priority'); }
    public function analyticsLevel(Clinic $clinic): string        { return (string) $this->value($clinic, 'analytics_level'); }
    public function hasFullAnalytics(Clinic $clinic): bool        { return $this->analyticsLevel($clinic) === SubscriptionPackage::ANALYTICS_FULL; }

    // ─────────────────────────── Usage counters ───────────────────────────

    public function servicesUsed(Clinic $clinic): int
    {
        return Service::where('clinic_id', $clinic->id)->count();
    }

    public function articlesUsedThisMonth(Clinic $clinic): int
    {
        return Article::where('clinic_id', $clinic->id)
            ->where('is_published', true)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
    }

    public function quoteRepliesUsedThisMonth(Clinic $clinic): int
    {
        return PriceQuoteReply::where('clinic_id', $clinic->id)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
    }

    // ─────────────────────────── Composite "can do X" checks ───────────────────────────

    /** True when the clinic still has room to publish another article this month. */
    public function canPublishArticle(Clinic $clinic): bool
    {
        $limit = $this->articlesLimit($clinic);
        if ($limit === null) return true; // unlimited
        return $this->articlesUsedThisMonth($clinic) < $limit;
    }

    public function canAddService(Clinic $clinic): bool
    {
        $limit = $this->servicesLimit($clinic);
        if ($limit === null) return true;
        return $this->servicesUsed($clinic) < $limit;
    }

    public function canReplyToQuote(Clinic $clinic): bool
    {
        $limit = $this->quoteRepliesLimit($clinic);
        if ($limit === null) return true;
        return $this->quoteRepliesUsedThisMonth($clinic) < $limit;
    }

    /**
     * Snapshot the gate's view of a clinic — feeds the React UI so
     * the package badges + usage meters render off a single API call.
     */
    public function snapshot(Clinic $clinic): array
    {
        $pkg = $this->resolve($clinic);
        return [
            'package' => $pkg ? [
                'id'           => $pkg->id,
                'slug'         => $pkg->slug,
                'name_ar'      => $pkg->name_ar,
                'name_en'      => $pkg->name_en,
                'color_token'  => $pkg->color_token,
                'monthly_price'=> $pkg->monthly_price,
            ] : null,
            'limits' => [
                'services'        => $this->servicesLimit($clinic),
                'articles_month'  => $this->articlesLimit($clinic),
                'quote_replies'   => $this->quoteRepliesLimit($clinic),
                'banner_slots'    => $this->bannerSlots($clinic),
            ],
            'usage' => [
                'services'       => $this->servicesUsed($clinic),
                'articles_month' => $this->articlesUsedThisMonth($clinic),
                'quote_replies'  => $this->quoteRepliesUsedThisMonth($clinic),
            ],
            'flags' => [
                'ai_article_generation'     => $this->canGenerateAiArticle($clinic),
                'featured_in_search'        => $this->isFeaturedInSearch($clinic),
                'ai_assistant_priority'     => $this->aiAssistantPriority($clinic),
                'google_reviews_sync'       => $this->hasGoogleReviewsSync($clinic),
                'verified_badge'            => $this->hasVerifiedBadge($clinic),
                'analytics_level'           => $this->analyticsLevel($clinic),
                'allow_offers_packages'     => $this->canPublishOffers($clinic),
                'allow_doctors_before_after'=> $this->canShowDoctorsAndBeforeAfter($clinic),
            ],
        ];
    }

    // ─────────────────────────── Internals ───────────────────────────

    /**
     * Load the clinic's package, caching by id for the request lifetime.
     *
     * Uses array_key_exists rather than isset so a cached `null`
     * (clinic points at a deleted package) stays cached — otherwise
     * every gate call re-hits the DB for the same dead FK.
     */
    private function resolve(Clinic $clinic): ?SubscriptionPackage
    {
        if ($clinic->subscription_package_id === null) return null;
        if (! array_key_exists($clinic->subscription_package_id, $this->packageCache)) {
            $this->packageCache[$clinic->subscription_package_id] =
                SubscriptionPackage::find($clinic->subscription_package_id);
        }
        return $this->packageCache[$clinic->subscription_package_id];
    }

    /** Read a feature column from the package, or fall back to the Free defaults. */
    private function value(Clinic $clinic, string $field): mixed
    {
        $pkg = $this->resolve($clinic);
        return $pkg?->{$field} ?? self::FALLBACK[$field] ?? null;
    }

    /**
     * Distinct from value() because nullable INT columns mean
     * "unlimited" — we need to preserve null, not coalesce to 0.
     */
    private function intOrNull(Clinic $clinic, string $field): ?int
    {
        $pkg = $this->resolve($clinic);
        if (! $pkg) {
            return self::FALLBACK[$field] ?? null;
        }
        $v = $pkg->{$field};
        return $v === null ? null : (int) $v;
    }
}
