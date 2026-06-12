<?php

namespace App\Http\Controllers\Public;

use App\Enums\ImpressionSource;
use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Service;
use App\Services\ImpressionTrackerService;
use App\Services\SimilarityService;

class DoctorController extends Controller
{
    /**
     * Doctor profile page: bio + the complex/sub-clinic they belong to, plus
     * (optional) the services of their specialty. Scoped under the complex
     * slug; a doctor not belonging to that complex 404s.
     */
    public function show(string $slug, Doctor $doctor, SimilarityService $similarity)
    {
        $clinic = Clinic::publiclyVisible()->where('slug', $slug)->firstOrFail();

        abort_unless($doctor->clinic_id === $clinic->id && $doctor->is_active, 404);

        $doctor->setRelation('clinic', $clinic);
        $doctor->load('subClinic:id,name,name_en,category_id,clinic_id', 'subClinic.category:id,name,emoji');

        // Opening a doctor profile counts as an appearance for the complex —
        // feeds the public «عدد الظهور» badge; failure-isolated in the tracker.
        app(ImpressionTrackerService::class)->trackClinic($clinic->id, ImpressionSource::PROFILE);

        // Services the clinic explicitly linked to this doctor take priority.
        // When none are linked we fall back to the doctor's sub-clinic services
        // (their specialty area) so existing profiles don't go blank.
        $services = $doctor->services()
            ->approvedPublic()
            ->with(['inlineOffer'])
            ->orderBy('sort_order')
            ->get();

        if ($services->isEmpty() && $doctor->sub_clinic_id) {
            $services = Service::query()
                ->where('sub_clinic_id', $doctor->sub_clinic_id)
                ->approvedPublic()
                ->with(['inlineOffer'])
                ->orderBy('sort_order')
                ->get();
        }

        $similarDoctors = $similarity->similarDoctors($doctor);

        return view('public.doctor', compact('clinic', 'doctor', 'services', 'similarDoctors'));
    }
}
