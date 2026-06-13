<?php

namespace App\Http\Controllers\Public;

use App\Enums\ImpressionSource;
use App\Http\Controllers\Controller;
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

        // Multi-source impression tracking: every clinic or offer-linked
        // service that surfaces in a homepage section attributes to the
        // HOME source. Cascade rules handle the clinic+service split.
        $this->trackHomeImpressions($sections);

        return view('public.home', [
            'sections' => $sections,
            'user'     => $user,
        ]);
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

            if (in_array($type, ['clinic_list', 'followed_clinics'], true) && ! empty($data['clinics'])) {
                foreach ($data['clinics'] as $clinic) {
                    if ($clinic?->id) {
                        $clinicIds[] = $clinic->id;
                    }
                }
            }

            if (in_array($type, ['offers', 'category_offers', 'followed_offers'], true) && ! empty($data['offers'])) {
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
