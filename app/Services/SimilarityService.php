<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Offer;
use App\Models\Service;
use App\Models\SubClinic;
use App\Models\SubscriptionPackage;
use Illuminate\Support\Collection;

/**
 * Single source of truth for every "similar / related" block surfaced on
 * the public site (offers, services, sub-clinics, doctors, complexes). Each
 * method answers the same shape of question — "more like THIS, from OTHER
 * publicly-visible complexes" — so the rules stay consistent across every
 * page that renders the shared `partials.similar-cards` view.
 *
 * Behaviour is driven PER SUBSCRIPTION PACKAGE: the package of the clinic
 * that OWNS the page being viewed decides, for each section type, whether it
 * shows and how it matches (city / specialty / sub-clinic), plus how many
 * cards. The super-admin edits this matrix in the package dialog; see
 * SubscriptionPackage::similarConfig(). A clinic with no package falls back
 * to SubscriptionPackage::defaultSimilarConfig() (everything on, 6 cards).
 *
 * A section whose `show` is false returns an empty collection, so the view's
 * `@if($items->isNotEmpty())` hides it with no further wiring.
 *
 * "Specialty" (التخصص) is derived per entity:
 *   - Offer     → its service's categories, or (general offer) its clinic's.
 *   - Service   → its categories.
 *   - SubClinic → its single category_id.
 *   - Doctor    → its free-text `specialty` and/or its sub-clinic's category.
 *   - Clinic    → its many-to-many categories.
 */
class SimilarityService
{
    /**
     * Resolve the effective config (show / match flags / limit) for one
     * section type from the page-owner clinic's package, with defaults.
     */
    private function resolve(?Clinic $clinic, string $type): array
    {
        $package = $clinic ? $clinic->loadMissing('package')->package : null;

        $section = $package
            ? $package->similarSection($type)
            : SubscriptionPackage::defaultSimilarConfig()['sections'][$type];
        $limit = $package
            ? $package->similarConfig()['limit']
            : SubscriptionPackage::defaultSimilarConfig()['limit'];

        return [
            'show'      => (bool) ($section['show'] ?? true),
            'city'      => (bool) ($section['match_city'] ?? true),
            'specialty' => (bool) ($section['match_specialty'] ?? true),
            'subclinic' => (bool) ($section['match_subclinic'] ?? true),
            'limit'     => max(1, (int) $limit),
        ];
    }

    /**
     * Constrain a related-entity query to OTHER publicly-visible complexes,
     * honouring the same-city flag. `$cityId` may be null (entity's complex
     * has no city) — in that case same-city matching can't succeed.
     */
    private function applyClinicScope($query, bool $matchCity, ?int $cityId): void
    {
        $query->whereHas('clinic', function ($q) use ($matchCity, $cityId) {
            $q->publiclyVisible();
            if ($matchCity && $cityId) {
                $q->where('city_id', $cityId);
            }
        });
    }

    /**
     * Offers running now in OTHER complexes whose specialty overlaps this
     * offer's. Cards deep-link to the offer detail page.
     */
    public function similarOffers(Offer $offer): Collection
    {
        $clinic = $offer->clinic;
        $cfg    = $this->resolve($clinic, 'offer');
        if (! $cfg['show']) {
            return collect();
        }

        $cityId = $clinic?->city_id;
        if ($cfg['city'] && ! $cityId) {
            return collect();
        }

        $query = Offer::query()
            ->runningNow()
            ->where('clinic_id', '!=', $clinic->id);
        $this->applyClinicScope($query, $cfg['city'], $cityId);

        if ($cfg['specialty']) {
            $categoryIds = $offer->service
                ? $offer->service->categories()->pluck('categories.id')
                : $clinic->categories()->pluck('categories.id');
            if ($categoryIds->isEmpty()) {
                return collect();
            }
            $query->where(function ($q) use ($categoryIds) {
                $q->whereHas('service.categories', fn ($c) => $c->whereIn('categories.id', $categoryIds))
                    ->orWhereHas('clinic.categories', fn ($c) => $c->whereIn('categories.id', $categoryIds));
            });
        }

        return $query
            ->with([
                'clinic:id,name,slug,city_id',
                'clinic.city:id,name,name_en',
                'service:id,name,image',
            ])
            ->orderByDesc('is_featured')
            ->latest('starts_at')
            ->take($cfg['limit'])
            ->get();
    }

    /**
     * Priced services of the same specialty in OTHER complexes, cheapest-first
     * so the visitor can price-compare. Cards link to the service detail page.
     */
    public function similarServices(Service $service): Collection
    {
        $clinic = $service->clinic;
        $cfg    = $this->resolve($clinic, 'service');
        if (! $cfg['show']) {
            return collect();
        }

        $cityId = $clinic?->city_id;
        if ($cfg['city'] && ! $cityId) {
            return collect();
        }

        $query = Service::query()
            ->approvedPublic()
            ->whereNotNull('price')
            ->where('clinic_id', '!=', $clinic->id);
        $this->applyClinicScope($query, $cfg['city'], $cityId);

        if ($cfg['specialty']) {
            $categoryIds = $service->categories()->pluck('categories.id');
            if ($categoryIds->isEmpty()) {
                return collect();
            }
            $query->whereHas('categories', fn ($c) => $c->whereIn('categories.id', $categoryIds));
        }

        return $query
            ->with([
                'clinic:id,name,slug,city_id',
                'clinic.city:id,name,name_en',
                'categories:id,name,emoji',
            ])
            ->orderBy('price')
            ->take($cfg['limit'])
            ->get();
    }

    /**
     * Sub-clinics of the same specialty (category) in OTHER complexes. Cards
     * link to the sub-clinic detail page.
     */
    public function similarSubClinics(SubClinic $subClinic): Collection
    {
        $clinic = $subClinic->clinic;
        $cfg    = $this->resolve($clinic, 'subClinic');
        if (! $cfg['show']) {
            return collect();
        }

        $cityId = $clinic?->city_id;
        if ($cfg['city'] && ! $cityId) {
            return collect();
        }

        $query = SubClinic::query()
            ->where('is_active', true)
            ->where('clinic_id', '!=', $clinic->id);
        $this->applyClinicScope($query, $cfg['city'], $cityId);

        if ($cfg['specialty']) {
            if (! $subClinic->category_id) {
                return collect();
            }
            $query->where('category_id', $subClinic->category_id);
        }

        return $query
            ->with([
                'clinic:id,name,slug,city_id',
                'clinic.city:id,name,name_en',
                'category:id,name,emoji',
            ])
            ->orderBy('sort_order')
            ->take($cfg['limit'])
            ->get();
    }

    /**
     * Doctors in OTHER complexes matching the same free-text specialty and/or
     * the same sub-clinic category. Cards link to the doctor detail page.
     */
    public function similarDoctors(Doctor $doctor): Collection
    {
        $clinic = $doctor->clinic;
        $cfg    = $this->resolve($clinic, 'doctor');
        if (! $cfg['show']) {
            return collect();
        }

        $cityId = $clinic?->city_id;
        if ($cfg['city'] && ! $cityId) {
            return collect();
        }

        $query = Doctor::query()
            ->where('is_active', true)
            ->where('clinic_id', '!=', $clinic->id);
        $this->applyClinicScope($query, $cfg['city'], $cityId);

        // Specialty matching for doctors = free-text specialty (specialty
        // flag) OR same sub-clinic category (sub-clinic flag).
        $matchSpecialty = $cfg['specialty'] && $doctor->specialty;
        $matchSubType   = $cfg['subclinic'] && $doctor->subClinic?->category_id;

        if (($cfg['specialty'] || $cfg['subclinic']) && ! $matchSpecialty && ! $matchSubType) {
            // A specialty constraint is requested but this doctor exposes
            // nothing to match on → no meaningful "similar" set.
            return collect();
        }

        if ($matchSpecialty || $matchSubType) {
            $specialty     = $doctor->specialty;
            $subCategoryId = $doctor->subClinic?->category_id;
            $query->where(function ($q) use ($matchSpecialty, $matchSubType, $specialty, $subCategoryId) {
                if ($matchSpecialty) {
                    $q->where('specialty', $specialty);
                }
                if ($matchSubType) {
                    $q->orWhereHas('subClinic', fn ($s) => $s->where('category_id', $subCategoryId));
                }
            });
        }

        return $query
            ->with([
                'clinic:id,name,slug,city_id',
                'clinic.city:id,name,name_en',
                'subClinic:id,name,name_en,category_id',
            ])
            ->orderBy('sort_order')
            ->take($cfg['limit'])
            ->get();
    }

    /**
     * Other publicly-visible complexes with overlapping specialties — the
     * generic "similar complexes" strip for pages that aren't an
     * offer/service/sub-clinic/doctor. Cards link to the complex page.
     */
    public function similarClinics(Clinic $clinic): Collection
    {
        $cfg = $this->resolve($clinic, 'complex');
        if (! $cfg['show']) {
            return collect();
        }

        $cityId = $clinic->city_id;
        if ($cfg['city'] && ! $cityId) {
            return collect();
        }

        // Columns are qualified because rankedForListing() joins the
        // subscription_packages table, making bare `id` / `city_id` ambiguous.
        $query = Clinic::publiclyVisible()
            ->where('clinics.id', '!=', $clinic->id);

        if ($cfg['city']) {
            $query->where('clinics.city_id', $cityId);
        }

        if ($cfg['specialty']) {
            $categoryIds = $clinic->categories()->pluck('categories.id');
            if ($categoryIds->isNotEmpty()) {
                $query->whereHas('categories', fn ($c) => $c->whereIn('categories.id', $categoryIds));
            }
        }

        return $query
            ->with(['city:id,name,name_en', 'categories:id,name,emoji'])
            ->withAvg('googleReviews', 'rating')
            ->withCount('googleReviews')
            ->rankedForListing()
            ->take($cfg['limit'])
            ->get();
    }
}
