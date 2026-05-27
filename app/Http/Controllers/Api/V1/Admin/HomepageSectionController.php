<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ReorderHomepageSectionRequest;
use App\Http\Requests\Api\V1\Admin\UpdateHomepageSectionRequest;
use App\Http\Resources\Api\V1\HomepageSectionResource;
use App\Models\HomepageSection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class HomepageSectionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', HomepageSection::class);

        // Always return the full list — there are ~16 sections, no need to paginate.
        $sections = HomepageSection::query()
            ->withCount('bannerSlides')
            ->orderBy('sort_order')
            ->get();

        return HomepageSectionResource::collection($sections);
    }

    public function show(HomepageSection $homepageSection): HomepageSectionResource
    {
        $this->authorize('view', $homepageSection);

        return new HomepageSectionResource($homepageSection->load('bannerSlides'));
    }

    public function update(UpdateHomepageSectionRequest $request, HomepageSection $homepageSection): HomepageSectionResource
    {
        $homepageSection->update($request->validated());

        return new HomepageSectionResource($homepageSection->fresh()->load('bannerSlides'));
    }

    public function reorder(ReorderHomepageSectionRequest $request): JsonResponse
    {
        DB::transaction(function () use ($request) {
            foreach ($request->validated()['order'] as $row) {
                HomepageSection::where('id', $row['id'])->update(['sort_order' => $row['sort_order']]);
            }
        });

        // Bust the public cache so the new order is reflected immediately.
        \Illuminate\Support\Facades\Cache::forget(HomepageSection::CACHE_KEY);

        return response()->json(['message' => 'Reordered.']);
    }
}
