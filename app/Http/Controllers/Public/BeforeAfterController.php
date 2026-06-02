<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\BeforeAfterPhoto;
use App\Models\Clinic;
use App\Services\FeatureGate;

class BeforeAfterController extends Controller
{
    /**
     * Before/after case detail page — both images shown large with the
     * linked service / sub-clinic context. Scoped under the complex slug;
     * a photo not belonging to the complex, inactive, or whose package tier
     * can't show doctors/before-after, 404s.
     */
    public function show(string $slug, BeforeAfterPhoto $photo, FeatureGate $gate)
    {
        $clinic = Clinic::publiclyVisible()->where('slug', $slug)->firstOrFail();

        abort_unless(
            $photo->clinic_id === $clinic->id
                && $photo->is_active
                && $gate->canShowDoctorsAndBeforeAfter($clinic),
            404,
        );

        $photo->setRelation('clinic', $clinic);
        $photo->load(['service:id,name,clinic_id', 'subClinic:id,name,name_en,clinic_id']);

        return view('public.before-after', compact('clinic', 'photo'));
    }
}
