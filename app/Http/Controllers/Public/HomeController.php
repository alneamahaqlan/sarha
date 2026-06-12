<?php

namespace App\Http\Controllers\Public;

use App\Enums\ImpressionSource;
use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Offer;
use App\Models\User;
use App\Services\HomepageRenderService;
use App\Services\ImpressionTrackerService;
use Illuminate\Support\Collection;

class HomeController extends Controller
{
    public function __construct(
        private readonly HomepageRenderService $renderer,
        private readonly ImpressionTrackerService $tracker,
    ) {
    }

    /**
     * The homepage is now a CMS. Sections, their order, and per-section
     * config (title overrides, item limits, scheduling, mobile visibility)
     * all live in homepage_sections. The view just iterates and includes
     * `public.sections.<type>` for each renderable row.
     */
    public function index()
    {
        $sections = $this->renderer->build();
        $user     = auth('web')->user();

        // Personalised "complexes you follow" strip + their live offers,
        // shown above the CMS sections for signed-in followers only.
        [$followedClinics, $followedOffers] = $this->followedData($user);

        // Multi-source impression tracking: every clinic or offer-linked
        // service that surfaces in a homepage section attributes to the
        // HOME source. Cascade rules handle the clinic+service split.
        $this->trackHomeImpressions($sections);

        return view('public.home', [
            'sections'        => $sections,
            'user'            => $user,
            'followedClinics' => $followedClinics,
            'followedOffers'  => $followedOffers,
        ]);
    }

    /**
     * The complexes the signed-in customer follows (still publicly
     * visible) plus the offers running right now across those complexes.
     * Returns [Collection $clinics, Collection $offers]; both empty for
     * guests or customers who don't follow anyone.
     *
     * @return array{0: Collection, 1: Collection}
     */
    private function followedData(?User $user): array
    {
        if (! $user) {
            return [collect(), collect()];
        }

        $followedIds = $user->following()->pluck('clinics.id');
        if ($followedIds->isEmpty()) {
            return [collect(), collect()];
        }

        $minPrice = fn ($q) => $q->where('is_active', true)->where('approval_status', 'approved')->whereNotNull('price');

        $clinics = Clinic::publiclyVisible()
            ->whereIn('clinics.id', $followedIds)
            ->with(['city', 'categories'])
            ->withAvg('googleReviews', 'rating')
            ->withCount('bookings')
            ->withMin(['services as min_price' => $minPrice], 'price')
            ->rankedForListing()
            ->limit(12)
            ->get();

        $offers = Offer::query()
            ->runningNow()
            ->whereIn('clinic_id', $followedIds)
            ->whereHas('clinic', fn ($c) => $c->publiclyVisible())
            ->with([
                'clinic:id,name,slug,city_id',
                'clinic.city:id,name',
                'service:id,name,image',
                'service.categories:id,name,name_en,slug,emoji',
            ])
            ->orderByDesc('is_featured')
            ->orderByDesc('starts_at')
            ->limit(8)
            ->get();

        return [$clinics, $offers];
    }

    /**
     * Walk the built sections and bump impressions for whichever
     * clinics/services they expose visually.
     *
     * - clinic_list      → bump each clinic in `$data['clinics']`
     * - offers           → bump each offer's service (cascades to clinic)
     * - category_offers  → same
     *
     * Other section types (hero, stats, articles, banners, map…) don't
     * surface a specific clinic by name in a way that counts as an
     * impression for the spec, so they're skipped.
     */
    private function trackHomeImpressions(Collection $sections): void
    {
        $clinicIds = [];
        $services  = [];

        foreach ($sections as $row) {
            $type = $row['section']->type ?? null;
            $data = $row['data'] ?? [];

            if ($type === 'clinic_list' && ! empty($data['clinics'])) {
                foreach ($data['clinics'] as $clinic) {
                    if ($clinic?->id) {
                        $clinicIds[] = $clinic->id;
                    }
                }
            }

            if (in_array($type, ['offers', 'category_offers'], true) && ! empty($data['offers'])) {
                foreach ($data['offers'] as $offer) {
                    if ($offer?->service) {
                        $services[] = $offer->service;
                    } elseif ($offer?->clinic_id) {
                        // General offers (no service) still surface a clinic.
                        $clinicIds[] = $offer->clinic_id;
                    }
                }
            }
        }

        if (! empty($services)) {
            $this->tracker->trackManyServices($services, ImpressionSource::HOME);
        }
        if (! empty($clinicIds)) {
            $this->tracker->trackManyClinics($clinicIds, ImpressionSource::HOME);
        }
    }
}
