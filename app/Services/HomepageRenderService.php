<?php

namespace App\Services;

use App\Models\Category;
use App\Models\City;
use App\Models\Clinic;
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
    /** @return Collection<int, array{section: HomepageSection, data: array}> */
    public function build(): Collection
    {
        $sections = Cache::remember(
            HomepageSection::CACHE_KEY,
            HomepageSection::CACHE_TTL,
            fn () => HomepageSection::renderable()->with('bannerSlides')->get(),
        );

        return $sections->map(fn (HomepageSection $s) => [
            'section' => $s,
            'data'    => $this->dataFor($s),
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
            'map'             => ['mapClinics' => $this->mapClinics($s->item_limit ?? 200)],
            // Static sections (hero already covered above; the rest are pure markup).
            'ai_highlight', 'how_it_works', 'cta' => [],
            default => [],
        };
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
        return Category::where('is_active', true)->orderBy('sort_order')->limit($limit)->get();
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
                'clinic:id,name,slug,city_id',
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
