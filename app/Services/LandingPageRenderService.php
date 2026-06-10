<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\LandingPage;
use Illuminate\Support\Collection;

/**
 * Resolves the auto-pulled data a landing page needs to render, based on its
 * type and linked entity. Reuses the existing Clinic relations + scopes so the
 * landing block partials can delegate to the clinic partials unchanged.
 */
class LandingPageRenderService
{
    public function __construct(private readonly LandingPageBuilderService $builder)
    {
    }

    /**
     * Build the view-model for a landing page.
     *
     * @return array{page: LandingPage, blocks: Collection, clinic: ?Clinic, comparisonClinics: Collection}
     */
    public function viewModel(LandingPage $page): array
    {
        $clinic = $this->contextClinic($page);
        $comparisonClinics = $page->type === 'comparison'
            ? $this->comparisonClinics($page)
            : collect();
        $listClinics = in_array($page->type, ['city', 'category'], true)
            ? $this->listClinics($page)
            : collect();

        return [
            'page'              => $page,
            'blocks'            => $this->builder->forPublic($page->id),
            'clinic'            => $clinic,
            'comparisonClinics' => $comparisonClinics,
            'listClinics'       => $listClinics,
        ];
    }

    /** Publicly-visible complexes for a city/category landing page. */
    public function listClinics(LandingPage $page): Collection
    {
        $query = Clinic::publiclyVisible()
            ->with(['city', 'categories'])
            ->withAvg('googleReviews', 'rating')
            ->withCount('googleReviews');

        if ($page->type === 'city' && $page->city_id) {
            $query->where('city_id', $page->city_id);
        } elseif ($page->type === 'category' && $page->category_id) {
            $query->whereHas('categories', fn ($q) => $q->whereKey($page->category_id));
        } else {
            return collect();
        }

        return $query->when(
            method_exists(Clinic::class, 'scopeRankedForListing'),
            fn ($q) => $q->rankedForListing(),
            fn ($q) => $q->latest(),
        )->limit(24)->get();
    }

    /**
     * The single complex whose data drives most blocks (services, doctors,
     * gallery, reviews, map, booking). Clinic-type → the linked clinic;
     * offer-type → the offer's clinic. Other types have no single context
     * clinic (city/category list many; custom has none) → null.
     */
    public function contextClinic(LandingPage $page): ?Clinic
    {
        $clinicId = match ($page->type) {
            'clinic' => $page->clinic_id,
            'offer'  => $page->offer?->clinic_id ?? $page->loadMissing('offer')->offer?->clinic_id,
            default  => $page->clinic_id, // custom pages may optionally link a clinic
        };

        if (! $clinicId) {
            return null;
        }

        return Clinic::publiclyVisible()
            ->whereKey($clinicId)
            ->with([
                'city',
                'services' => fn ($q) => $q->where('is_active', true)->where('approval_status', 'approved')->orderBy('sort_order'),
                'doctors',
                'offers' => fn ($q) => $q->orderByDesc('is_featured')->orderByDesc('starts_at'),
                'offers.service:id,name,price,image',
                'beforeAfterPhotos.service:id,name',
                'beforeAfterPhotos.subClinic:id,name,name_en',
                'googleReviews' => fn ($q) => $q->where('is_visible', true)->latest('reviewed_at')->limit(20),
                'workingHours' => fn ($q) => $q->orderBy('day_of_week'),
            ])
            ->withAvg('googleReviews', 'rating')
            ->withCount('googleReviews')
            ->first();
    }

    /** The ordered, publicly-visible complexes in a comparison page. */
    public function comparisonClinics(LandingPage $page): Collection
    {
        return $page->clinics()
            ->publiclyVisible()
            ->with(['city', 'categories'])
            ->withAvg('googleReviews', 'rating')
            ->withCount('googleReviews')
            ->get();
    }
}
