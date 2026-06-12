<?php

namespace App\Http\Controllers\Public;

use App\Enums\ImpressionSource;
use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Offer;
use App\Services\ImpressionTrackerService;
use App\Services\SimilarityService;

class OfferController extends Controller
{
    /**
     * Standalone offer detail page. Reached from any offer card on the site
     * (homepage, complex page, similar strips) — clicking an offer lands
     * HERE first, not on the booking form. The "book" CTA on this page is
     * what deep-links to booking.
     *
     * Scoped under the complex slug for context + correct canonical URL;
     * a mismatched offer/slug pair 404s rather than silently re-pointing.
     */
    public function show(string $slug, Offer $offer, SimilarityService $similarity)
    {
        $clinic = Clinic::publiclyVisible()->where('slug', $slug)->firstOrFail();

        abort_unless($offer->clinic_id === $clinic->id, 404);
        // Only live offers are publicly browsable (paused/expired/scheduled 404).
        abort_unless($offer->status() === 'active', 404);

        $offer->setRelation('clinic', $clinic);
        $offer->load(['service:id,name,price,image,sub_clinic_id', 'service.categories:id,name,emoji']);

        // Opening an offer page counts as an appearance for the complex
        // (and the linked service, if any). Feeds the public «عدد الظهور»
        // badge; failure-isolated inside the tracker.
        $tracker = app(ImpressionTrackerService::class);
        if ($offer->service) {
            $tracker->trackService($offer->service, ImpressionSource::PROFILE);
        } else {
            $tracker->trackClinic($clinic->id, ImpressionSource::PROFILE);
        }

        $similarOffers = $similarity->similarOffers($offer);

        // Surfacing other complexes' offers in the "similar offers" strip
        // counts as a SIMILAR appearance for each surfaced complex.
        $tracker->trackManyClinics($similarOffers->pluck('clinic_id')->all(), ImpressionSource::SIMILAR);

        return view('public.offer', compact('clinic', 'offer', 'similarOffers'));
    }
}
