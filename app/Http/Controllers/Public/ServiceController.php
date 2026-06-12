<?php

namespace App\Http\Controllers\Public;

use App\Enums\ImpressionSource;
use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Service;
use App\Services\ImpressionTrackerService;
use App\Services\SimilarityService;

class ServiceController extends Controller
{
    /**
     * Standalone service detail page. Reached by clicking a service anywhere
     * on the site — the visitor lands HERE first; the booking deep-link lives
     * on this page's CTA. Scoped under the complex slug; a service that
     * doesn't belong to that complex (or isn't publicly approved) 404s.
     */
    public function show(string $slug, Service $service, SimilarityService $similarity)
    {
        $clinic = Clinic::publiclyVisible()->where('slug', $slug)->firstOrFail();

        abort_unless(
            $service->clinic_id === $clinic->id
                && $service->is_active
                && $service->approval_status === Service::APPROVAL_APPROVED,
            404,
        );

        $service->setRelation('clinic', $clinic);
        $service->load([
            'subClinic:id,name,name_en,clinic_id',
            'categories:id,name,emoji',
        ]);

        // Opening a service page counts as an appearance for both the
        // service and (cascaded) its complex — feeds the public «عدد الظهور»
        // badge. Failure-isolated inside the tracker; never breaks the page.
        app(ImpressionTrackerService::class)->trackService($service, ImpressionSource::PROFILE);

        $similarServices = $similarity->similarServices($service);

        // Surfacing other complexes' services in the "similar services" strip
        // counts as a SIMILAR appearance for each (cascades to its complex).
        app(ImpressionTrackerService::class)
            ->trackManyServices($similarServices->all(), ImpressionSource::SIMILAR);

        return view('public.service', compact('clinic', 'service', 'similarServices'));
    }
}
