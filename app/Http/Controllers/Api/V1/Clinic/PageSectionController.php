<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clinic\ReorderPageSectionsRequest;
use App\Http\Requests\Api\V1\Clinic\UpdatePageSectionRequest;
use App\Http\Resources\Api\V1\ClinicPageSectionResource;
use App\Models\ClinicPageSection;
use App\Services\ClinicPageBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Clinic Page Builder — each authenticated clinic manages the 14 section
 * rows that drive its own public /clinic/{slug} page. Strictly scoped to
 * auth('clinic')->id().
 */
class PageSectionController extends Controller
{
    public function __construct(private readonly ClinicPageBuilderService $builder)
    {
    }

    public function index(): AnonymousResourceCollection
    {
        $clinicId = (int) auth('clinic')->id();

        return ClinicPageSectionResource::collection(
            $this->builder->forAdmin($clinicId)
        );
    }

    public function update(UpdatePageSectionRequest $request, ClinicPageSection $section): ClinicPageSectionResource
    {
        $this->authorizeSection($section);

        $section->update($request->validated());

        return new ClinicPageSectionResource($section->fresh());
    }

    public function reorder(ReorderPageSectionsRequest $request): JsonResponse
    {
        $clinicId = (int) auth('clinic')->id();

        // Re-fetch the ids in this payload, filter to ones that belong to
        // this clinic — guards against an admin from clinic A trying to
        // reorder clinic B's rows even if they hit our endpoint with
        // crafted IDs.
        $payload = collect($request->validated()['order']);
        $ownedIds = ClinicPageSection::where('clinic_id', $clinicId)
            ->whereIn('id', $payload->pluck('id'))
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($payload, $ownedIds) {
            foreach ($payload as $row) {
                if (! in_array((int) $row['id'], $ownedIds, true)) {
                    continue;
                }
                ClinicPageSection::where('id', $row['id'])
                    ->update(['sort_order' => $row['sort_order']]);
            }
        });

        Cache::forget(ClinicPageSection::cacheKey($clinicId));

        return response()->json(['message' => 'Reordered.']);
    }

    /**
     * Strict scope guard — clinic A may never load/modify clinic B's
     * sections through route-model binding. Aborts 404 (not 403) to avoid
     * leaking whether a given ID exists at all.
     */
    private function authorizeSection(ClinicPageSection $section): void
    {
        $clinicId = (int) auth('clinic')->id();
        abort_if($section->clinic_id !== $clinicId, 404);
    }
}
