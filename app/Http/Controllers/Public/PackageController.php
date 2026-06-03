<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Package;
use App\Services\FeatureGate;

class PackageController extends Controller
{
    /**
     * Package (bundle) detail page. Reached by clicking a package anywhere on
     * the complex page; the booking deep-link lives on this page's CTA.
     * Scoped under the complex slug; a package that doesn't belong to the
     * complex, is inactive, or whose package tier can't publish offers/
     * packages, 404s.
     */
    public function show(string $slug, Package $package, FeatureGate $gate)
    {
        $clinic = Clinic::publiclyVisible()->where('slug', $slug)->firstOrFail();

        abort_unless(
            $package->clinic_id === $clinic->id
                && $package->is_active
                && $gate->canPublishOffers($clinic),
            404,
        );

        $package->setRelation('clinic', $clinic);
        $package->load(['services' => fn ($q) => $q->approvedPublic()->orderBy('sort_order')]);

        return view('public.package', compact('clinic', 'package'));
    }
}
