<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\Category;
use App\Models\City;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\HomepageSection;
use App\Models\Offer;
use App\Models\Service;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Fetches the homepage sections (admin-curated) plus the per-type data
 * each partial needs, so the controller just hands the result to the view.
 *
 * The section list itself is cached for 10 minutes (busted on any section
 * save); the data each section pulls is queried fresh per request because
 * counts/prices change often.
 */
class HomepageRenderService
{
    /**
     * Section types whose data depends on the *current viewer* (their follows),
     * so they must never be cached across requests — resolved fresh every time.
     */
    private const PERSONALIZED_TYPES = ['followed_offers', 'followed_clinics'];

    /** Short TTL (seconds) for the per-section data cache — bounds staleness of offers/prices. */
    private const DATA_CACHE_TTL = 120;

    /** @return Collection<int, array{section: HomepageSection, data: array}> */
    public function build(): Collection
    {
        $sections = Cache::remember(
            HomepageSection::CACHE_KEY,
            HomepageSection::CACHE_TTL,
            fn () => HomepageSection::renderable()->with('bannerSlides')->get(),
        );

        $locale = app()->getLocale();

        return $sections->map(fn (HomepageSection $s) => [
            'section' => $s,
            // Personalised sections resolve per-request; everything else is
            // shared across visitors, so cache its (DB-heavy) data for a short
            // window to keep the homepage off the database on every hit.
            'data'    => in_array($s->type, self::PERSONALIZED_TYPES, true)
                ? $this->dataFor($s)
                : Cache::remember(
                    "home:section:{$s->id}:{$locale}",
                    self::DATA_CACHE_TTL,
                    fn () => $this->dataFor($s),
                ),
        ]);
    }

    /** Type-specific data each partial expects in $data. */
    private function dataFor(HomepageSection $s): array
    {
        return match ($s->type) {
            'hero'            => ['cities' => $this->cities()],
            'stats'           => ['stats' => $this->stats()],
            'banner'          => ['slides' => $s->bannerSlides->where('is_active', true)->values()],
            'offers'          => ['offers' => $this->offers(null, $s->item_limit ?? 8, (int) data_get($s->config, 'min_discount', 0))],
            'articles'        => ['articles' => $this->articles($s->item_limit ?? 6)],
            'categories'      => ['categories' => $this->categories($s->item_limit ?? 14)],
            'category_offers' => $this->categoryOffersData($s),
            'clinic_list'     => $this->clinicListData($s),
            // Personalised: the signed-in customer's followed complexes + their
            // live offers. Resolved per-request (build()'s data map isn't
            // cached) so it always reflects the current viewer's follows.
            'followed_offers'  => ['offers'  => $this->followedOffers($s->item_limit ?? 8)],
            'followed_clinics' => ['clinics' => $this->followedClinics($s->item_limit ?? 12)],
            'map'             => ['mapClinics' => $this->mapClinics($s->item_limit ?? 200)],
            'faqs'            => ['faqs' => $this->faqs($s)],
            'badged'          => $this->badgedData($s),
            // Static sections (hero already covered above; the rest are pure markup).
            'ai_highlight', 'how_it_works', 'cta' => [],
            default => [],
        };
    }

    /**
     * Clean, fully-filled Q&A rows from the section's config bag, capped at
     * HomepageSection::FAQ_LIMIT. Drops any half-filled / empty rows so the
     * partial only ever renders complete pairs.
     *
     * @return array<int, array{question: string, answer: string}>
     */
    private function faqs(HomepageSection $s): array
    {
        return collect(data_get($s->config, 'faqs', []))
            ->map(fn ($row) => [
                'question' => trim((string) data_get($row, 'question', '')),
                'answer'   => trim((string) data_get($row, 'answer', '')),
            ])
            ->filter(fn ($row) => $row['question'] !== '' && $row['answer'] !== '')
            ->take(HomepageSection::FAQ_LIMIT)
            ->values()
            ->all();
    }

    // ── data builders ──────────────────────────────────────────────────────

    private function cities(): Collection
    {
        return City::where('is_active', true)->orderBy('sort_order')->get();
    }

    private function stats(): array
    {
        return Cache::remember('home:stats:v1', now()->addHour(), fn () => [
            'clinics'     => Clinic::publiclyVisible()->count(),
            'cities'      => City::where('is_active', true)->count(),
            'specialties' => Category::where('is_active', true)->count(),
            'services'    => Service::where('is_active', true)->count(),
        ]);
    }

    private function categories(int $limit): Collection
    {
        // withCount('clinics') is display-only — powers the "N مجمع" badge on
        // each category tile (same metric PlatformStatsService::bySpecialty uses).
        return Category::where('is_active', true)
            ->withCount('clinics')
            ->orderBy('sort_order')
            ->limit($limit)
            ->get();
    }

    /**
     * Active Offer entities (running window + is_active). Optionally
     * narrowed by category (via the linked service's specialties) and by
     * minimum percentage so the "deep discounts" section can ask for only
     * 20%+ off, etc.
     *
     * General offers (no linked service) participate in the unfiltered
     * list but are excluded when a category filter is applied, since
     * they're not tied to a specialty.
     */
    private function offers(?int $categoryId, int $limit, int $minDiscountPercent = 0): Collection
    {
        $q = Offer::query()
            ->runningNow()
            ->with([
                // withAvg loads the clinic's average Google rating for the
                // premium offer card's ★ indicator (display-only). Explicit
                // column list kept so the avg alias is appended, not replacing.
                'clinic' => fn ($c) => $c->select('id', 'name', 'slug', 'city_id', 'cart_status', 'cart_storefront_enabled')
                    ->withAvg('googleReviews', 'rating'),
                'clinic.city:id,name',
                'service:id,name,image',
                'service.categories:id,name,name_en,slug,emoji',
            ])
            ->whereHas('clinic', fn ($c) => $c->publiclyVisible());

        if ($categoryId !== null) {
            // Category filtering implies "service-linked" — general offers
            // have no specialty to match against.
            $q->where('type', Offer::TYPE_SERVICE)
                ->whereHas('service.categories', fn ($c) => $c->where('categories.id', $categoryId));
        }

        if ($minDiscountPercent > 0) {
            // (old_price - price) / old_price >= minDiscount/100. Only
            // makes sense for offers that have both prices; the WHERE
            // protects against division by zero / NULL.
            $q->whereNotNull('old_price')
                ->whereNotNull('price')
                ->where('old_price', '>', 0)
                ->whereRaw('(old_price - price) / old_price >= ?', [$minDiscountPercent / 100]);
        }

        return $q->orderByDesc('is_featured')
            ->orderByDesc('starts_at')
            ->limit($limit)
            ->get();
    }

    private function articles(int $limit): Collection
    {
        // articles table may not be available in every install — defensive query.
        if (! \Schema::hasTable('articles')) {
            return collect();
        }
        return \App\Models\Article::query()
            ->with(['clinic:id,name,slug'])
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }

    /** Memoised ids of the complexes the current viewer follows (empty for guests). */
    private ?Collection $followedIds = null;

    private function followedClinicIds(): Collection
    {
        if ($this->followedIds !== null) {
            return $this->followedIds;
        }
        $user = auth('web')->user();

        return $this->followedIds = $user
            ? $user->following()->pluck('clinics.id')
            : collect();
    }

    /** Live offers across the viewer's followed complexes (empty → section self-hides). */
    private function followedOffers(int $limit): Collection
    {
        $ids = $this->followedClinicIds();
        if ($ids->isEmpty()) {
            return collect();
        }

        return Offer::query()
            ->runningNow()
            ->whereIn('clinic_id', $ids)
            ->whereHas('clinic', fn ($c) => $c->publiclyVisible())
            ->with([
                'clinic:id,name,slug,city_id',
                'clinic.city:id,name',
                'service:id,name,image',
                'service.categories:id,name,name_en,slug,emoji',
            ])
            ->orderByDesc('is_featured')
            ->orderByDesc('starts_at')
            ->limit($limit)
            ->get();
    }

    /** The viewer's followed complexes (still publicly visible). */
    private function followedClinics(int $limit): Collection
    {
        $ids = $this->followedClinicIds();
        if ($ids->isEmpty()) {
            return collect();
        }

        $minPrice = fn ($q) => $q->where('is_active', true)->where('approval_status', 'approved')->whereNotNull('price');

        return Clinic::publiclyVisible()
            ->whereIn('clinics.id', $ids)
            ->with(['city', 'categories'])
            ->withAvg('googleReviews', 'rating')
            ->withCount('bookings')
            ->withMin(['services as min_price' => $minPrice], 'price')
            ->rankedForListing()
            ->limit($limit)
            ->get();
    }

    /**
     * Entities carrying the admin-chosen badge. Reads config.badge_key +
     * config.target_type; returns the matching, publicly-visible entities for
     * the badged.blade.php partial (empty → section self-hides).
     */
    private function badgedData(HomepageSection $s): array
    {
        $key  = data_get($s->config, 'badge_key');
        $type = data_get($s->config, 'target_type', 'clinic');
        $limit = $s->item_limit ?? 8;

        $badge = $key ? Badge::active()->where('key', $key)->first() : null;
        if (! $badge) {
            return ['targetType' => $type, 'items' => collect(), 'badge' => null];
        }

        // Non-expired pivot rows only (manual + auto).
        $notExpired = fn ($q) => $q->where(fn ($qq) => $qq
            ->whereNull('badgeables.expires_at')->orWhere('badgeables.expires_at', '>', now()));

        $items = match ($type) {
            'offer'   => $this->badgedOffers($badge, $notExpired, $limit),
            'service' => $this->badgedServices($badge, $notExpired, $limit),
            'doctor'  => $this->badgedDoctors($badge, $notExpired, $limit),
            default   => $this->badgedClinics($badge, $notExpired, $limit),
        };

        return ['targetType' => $type, 'items' => $items, 'badge' => $badge];
    }

    private function badgedClinics(Badge $badge, callable $notExpired, int $limit): Collection
    {
        $ids = $notExpired($badge->clinics())->pluck('clinics.id');
        if ($ids->isEmpty()) {
            return collect();
        }
        $minPrice = fn ($q) => $q->where('is_active', true)->where('approval_status', 'approved')->whereNotNull('price');

        return Clinic::publiclyVisible()
            ->whereIn('clinics.id', $ids)
            ->with(['city', 'categories'])
            ->withAvg('googleReviews', 'rating')
            ->withCount('bookings')
            ->withMin(['services as min_price' => $minPrice], 'price')
            ->rankedForListing()
            ->limit($limit)
            ->get();
    }

    private function badgedOffers(Badge $badge, callable $notExpired, int $limit): Collection
    {
        $ids = $notExpired($badge->offers())->pluck('offers.id');
        if ($ids->isEmpty()) {
            return collect();
        }

        return Offer::query()
            ->runningNow()
            ->whereIn('id', $ids)
            ->whereHas('clinic', fn ($c) => $c->publiclyVisible())
            ->with([
                'clinic' => fn ($c) => $c->select('id', 'name', 'slug', 'city_id', 'cart_status', 'cart_storefront_enabled')
                    ->withAvg('googleReviews', 'rating'),
                'clinic.city:id,name',
                'service:id,name,image',
                'service.categories:id,name,name_en,slug,emoji',
            ])
            ->orderByDesc('is_featured')
            ->orderByDesc('starts_at')
            ->limit($limit)
            ->get();
    }

    private function badgedServices(Badge $badge, callable $notExpired, int $limit): Collection
    {
        $ids = $notExpired($badge->services())->pluck('services.id');
        if ($ids->isEmpty()) {
            return collect();
        }

        return Service::query()
            ->whereIn('services.id', $ids)
            ->where('is_active', true)
            ->where('approval_status', 'approved')
            ->whereHas('clinic', fn ($c) => $c->publiclyVisible())
            ->with(['clinic:id,name,slug', 'categories:id,name,name_en,slug,emoji'])
            ->limit($limit)
            ->get();
    }

    private function badgedDoctors(Badge $badge, callable $notExpired, int $limit): Collection
    {
        $ids = $notExpired($badge->doctors())->pluck('doctors.id');
        if ($ids->isEmpty()) {
            return collect();
        }

        return Doctor::query()
            ->whereIn('doctors.id', $ids)
            ->where('is_active', true)
            ->whereHas('clinic', fn ($c) => $c->publiclyVisible())
            ->with(['clinic:id,name,slug'])
            ->limit($limit)
            ->get();
    }

    private function categoryOffersData(HomepageSection $s): array
    {
        $slug = data_get($s->config, 'category_slug');
        $category = $slug ? Category::where('slug', $slug)->first() : null;
        return [
            'category' => $category,
            // Same key name 'offers' as the main offers section so the
            // category_offers partial can stay symmetric. Value is now a
            // Collection of Offer entities (was Service rows before).
            'offers'   => $category
                ? $this->offers($category->id, $s->item_limit ?? 6)
                : collect(),
        ];
    }

    private function clinicListData(HomepageSection $s): array
    {
        $source = data_get($s->config, 'source', 'featured');
        $limit  = $s->item_limit ?? 8;
        $minPrice = fn ($q) => $q->where('is_active', true)->where('approval_status', 'approved')->whereNotNull('price');

        $base = Clinic::publiclyVisible()
            ->with(['city', 'categories'])
            ->withAvg('googleReviews', 'rating')
            ->withCount('bookings')
            ->withMin(['services as min_price' => $minPrice], 'price');

        $clinics = match ($source) {
            // "Featured" now means either an admin manual boost OR a
            // package whose featured_in_search flag is on. rankedForListing
            // applies its own package-driven sort on top.
            'featured'    => $base
                ->withPackageFeatures()
                ->where(fn ($q) => $q->where('clinics.is_featured', true)->orWhereRaw('COALESCE(pkg.featured_in_search, 0) = 1'))
                ->rankedForListing()
                ->take($limit)->get(),
            'top_rated'   => $base->whereHas('googleReviews')->withCount('googleReviews')->orderByDesc('google_reviews_avg_rating')->take($limit)->get(),
            'best_priced' => $base->whereHas('services', fn ($q) => $q->where('is_active', true)->where('approval_status', 'approved')->whereNotNull('price'))->orderBy('min_price')->take($limit)->get(),
            default       => $base->rankedForListing()->take($limit)->get(),
        };

        return ['clinics' => $clinics];
    }

    private function mapClinics(int $limit): Collection
    {
        // Note: rankedForListing replaces the legacy is_featured sort
        // with the package-driven one. The 5-column ->get() projection
        // becomes clinics.* once the scope joins subscription_packages,
        // which is fine — these markers only need a handful of fields.
        return Clinic::publiclyVisible()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->rankedForListing()
            ->take($limit)
            ->get()
            ->map(fn (Clinic $c) => [
                'id'   => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'lat'  => (float) $c->latitude,
                'lng'  => (float) $c->longitude,
            ])
            ->values();
    }
}
