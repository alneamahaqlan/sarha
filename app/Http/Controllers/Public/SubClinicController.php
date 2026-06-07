<?php

namespace App\Http\Controllers\Public;

use App\Enums\ImpressionSource;
use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Offer;
use App\Models\Package;
use App\Models\Service;
use App\Models\SubClinic;
use App\Services\ImpressionTrackerService;
use App\Services\SimilarityService;

class SubClinicController extends Controller
{
    /**
     * A single clinic (عيادة) inside a complex (مجمع). Surfaces, in spec
     * order: its offers → packages → services → doctors. Scoped under the
     * complex slug; a sub-clinic that doesn't belong to that complex 404s.
     */
    public function show(string $slug, SubClinic $subClinic, SimilarityService $similarity)
    {
        $clinic = Clinic::publiclyVisible()->where('slug', $slug)->firstOrFail();

        abort_unless($subClinic->clinic_id === $clinic->id && $subClinic->is_active, 404);

        $subClinic->setRelation('clinic', $clinic);
        $subClinic->load('category:id,name,emoji');

        // Opening a sub-clinic page counts as an appearance for the complex —
        // feeds the public «عدد الظهور» badge; failure-isolated in the tracker.
        app(ImpressionTrackerService::class)->trackClinic($clinic->id, ImpressionSource::PROFILE);

        // Services that live directly under this sub-clinic (public gate).
        $services = Service::query()
            ->where('sub_clinic_id', $subClinic->id)
            ->approvedPublic()
            ->orderBy('sort_order')
            ->get();

        $serviceIds = $services->pluck('id');

        // Offers whose linked service belongs to this sub-clinic, live now.
        $offers = Offer::query()
            ->where('clinic_id', $clinic->id)
            ->runningNow()
            ->whereHas('service', fn ($q) => $q->where('sub_clinic_id', $subClinic->id))
            ->with('service:id,name,price,image')
            ->orderByDesc('is_featured')
            ->get();

        // Clinic packages that bundle at least one of this sub-clinic's services.
        $packages = $serviceIds->isNotEmpty()
            ? Package::query()
                ->where('clinic_id', $clinic->id)
                ->where('is_active', true)
                ->whereHas('services', fn ($q) => $q->whereIn('services.id', $serviceIds))
                ->with(['services:id,name'])
                ->orderBy('sort_order')
                ->get()
            : collect();

        $doctors = Doctor::query()
            ->where('sub_clinic_id', $subClinic->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $similarSubClinics = $similarity->similarSubClinics($subClinic);

        return view('public.sub-clinic', compact(
            'clinic', 'subClinic', 'offers', 'packages', 'services', 'doctors', 'similarSubClinics',
        ));
    }
}
