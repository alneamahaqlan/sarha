<?php

namespace App\Services;

use App\Models\Category;
use App\Models\City;
use App\Models\Clinic;
use App\Models\HomepageSection;
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
            'offers'          => ['services' => $this->offers(null, $s->item_limit ?? 8, (int) data_get($s->config, 'min_discount', 0))],
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
     * Services with an active discount. Optionally narrowed by category and
     * by minimum percentage so the "deep discounts" section can ask for only
     * 20%+ off, etc.
     */
    private function offers(?int $categoryId, int $limit, int $minDiscountPercent = 0): Collection
    {
        $q = Service::query()
            ->with(['clinic:id,name,slug,city_id', 'clinic.city:id,name', 'categories:id,name,name_en,slug,emoji'])
            ->where('is_active', true)
            ->whereNotNull('old_price')
            ->whereNotNull('offer_expires_at')
            ->where('offer_expires_at', '>', now())
            ->whereHas('clinic', fn ($c) => $c->publiclyVisible());

        if ($categoryId !== null) {
            $q->whereHas('categories', fn ($c) => $c->where('categories.id', $categoryId));
        }

        if ($minDiscountPercent > 0) {
            // (old_price - price) / old_price >= minDiscount/100
            $q->whereRaw('(old_price - price) / old_price >= ?', [$minDiscountPercent / 100]);
        }

        return $q->orderByDesc('is_featured_offer')
            ->orderByRaw('(old_price - price) / old_price DESC')
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
            'services' => $category
                ? $this->offers($category->id, $s->item_limit ?? 6)
                : collect(),
        ];
    }

    private function clinicListData(HomepageSection $s): array
    {
        $source = data_get($s->config, 'source', 'featured');
        $limit  = $s->item_limit ?? 8;
        $minPrice = fn ($q) => $q->where('is_active', true)->whereNotNull('price');

        $base = Clinic::publiclyVisible()
            ->with(['city', 'categories'])
            ->withAvg('googleReviews', 'rating')
            ->withCount('bookings')
            ->withMin(['services as min_price' => $minPrice], 'price');

        $clinics = match ($source) {
            'featured'    => $base->where('is_featured', true)->rankedForListing()->take($limit)->get(),
            'top_rated'   => $base->whereHas('googleReviews')->withCount('googleReviews')->orderByDesc('google_reviews_avg_rating')->take($limit)->get(),
            'best_priced' => $base->whereHas('services', fn ($q) => $q->where('is_active', true)->whereNotNull('price'))->orderBy('min_price')->take($limit)->get(),
            default       => $base->rankedForListing()->take($limit)->get(),
        };

        return ['clinics' => $clinics];
    }

    private function mapClinics(int $limit): Collection
    {
        return Clinic::publiclyVisible()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderByDesc('is_featured')
            ->take($limit)
            ->get(['id', 'name', 'slug', 'latitude', 'longitude'])
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
