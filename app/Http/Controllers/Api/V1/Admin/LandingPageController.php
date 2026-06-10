<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreLandingPageRequest;
use App\Http\Requests\Api\V1\Admin\UpdateLandingPageRequest;
use App\Http\Resources\Api\V1\LandingPageResource;
use App\Models\LandingPage;
use App\Services\LandingPageBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LandingPageController extends Controller
{
    public function __construct(private readonly LandingPageBuilderService $builder)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', LandingPage::class);

        $query = LandingPage::query();

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('title_ar', 'like', "%{$search}%")
                  ->orWhere('title_en', 'like', "%{$search}%")
                  ->orWhere('internal_name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($type = $request->string('filter.type')->toString()) {
            $query->where('type', $type);
        }

        if ($status = $request->string('filter.status')->toString()) {
            $query->where('status', $status);
        }

        $sort = $request->string('sort', '-created_at')->toString();
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $allowed = ['created_at', 'updated_at', 'title_ar', 'slug', 'status', 'type', 'total_views', 'total_conversions'];
        $query->orderBy(in_array($column, $allowed, true) ? $column : 'created_at', $direction);

        $perPage = min(max((int) $request->input('per_page', 30), 1), 100);

        return LandingPageResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function show(LandingPage $landingPage): LandingPageResource
    {
        $this->authorize('view', $landingPage);

        $landingPage->load(['blocks', 'clinics:id']);

        return new LandingPageResource($landingPage);
    }

    public function store(StoreLandingPageRequest $request): JsonResponse
    {
        $data = $request->validated();
        $comparisonIds = $data['comparison_clinic_ids'] ?? null;
        unset($data['comparison_clinic_ids']);

        $data['created_by'] = $request->user('admin')->id;

        $page = LandingPage::create($data);

        // Seed the default block roster for this page type so the builder
        // opens populated (mirrors ClinicPageBuilderService lazy-seed).
        $this->builder->seedDefaults($page);

        if ($page->type === 'comparison' && $comparisonIds) {
            $this->syncComparisonClinics($page, $comparisonIds);
        }

        $page->load(['blocks', 'clinics:id']);

        return (new LandingPageResource($page))->response()->setStatusCode(201);
    }

    public function update(UpdateLandingPageRequest $request, LandingPage $landingPage): LandingPageResource
    {
        $data = $request->validated();
        $comparisonIds = $data['comparison_clinic_ids'] ?? null;
        unset($data['comparison_clinic_ids']);

        $landingPage->update($data);

        if ($landingPage->type === 'comparison' && $comparisonIds !== null) {
            $this->syncComparisonClinics($landingPage, $comparisonIds);
        }

        $landingPage->load(['blocks', 'clinics:id']);

        return new LandingPageResource($landingPage);
    }

    public function destroy(LandingPage $landingPage): JsonResponse
    {
        $this->authorize('delete', $landingPage);

        $landingPage->delete();

        return response()->json(null, 204);
    }

    /** Attach the comparison clinics with their order, preserving the highlight flag. */
    private function syncComparisonClinics(LandingPage $page, array $clinicIds): void
    {
        $sync = [];
        foreach (array_values($clinicIds) as $i => $clinicId) {
            $sync[$clinicId] = ['sort_order' => ($i + 1) * 10];
        }

        $page->clinics()->sync($sync);
    }
}
