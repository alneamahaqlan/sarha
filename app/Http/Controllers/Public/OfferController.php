<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Offer;
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

        $similarOffers = $similarity->similarOffers($offer);

        return view('public.offer', compact('clinic', 'offer', 'similarOffers'));
    }
}
