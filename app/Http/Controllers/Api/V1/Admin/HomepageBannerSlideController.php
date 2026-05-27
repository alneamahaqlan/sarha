<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ReorderBannerSlideRequest;
use App\Http\Requests\Api\V1\Admin\StoreBannerSlideRequest;
use App\Http\Requests\Api\V1\Admin\UpdateBannerSlideRequest;
use App\Http\Resources\Api\V1\HomepageBannerSlideResource;
use App\Models\HomepageBannerSlide;
use App\Models\HomepageSection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Slides belong to a banner-type HomepageSection. All write paths are
 * scoped to the parent section so a slide can never get orphaned or
 * attached to a non-banner row.
 */
class HomepageBannerSlideController extends Controller
{
    public function index(HomepageSection $homepageSection): AnonymousResourceCollection
    {
        $this->authorize('view', $homepageSection);
        abort_unless($homepageSection->type === 'banner', 404);

        return HomepageBannerSlideResource::collection(
            $homepageSection->bannerSlides()->orderBy('sort_order')->get()
        );
    }

    public function store(StoreBannerSlideRequest $request, HomepageSection $homepageSection): JsonResponse
    {
        abort_unless($homepageSection->type === 'banner', 404);

        $payload = $request->validated();
        // If no explicit sort_order, append at the end.
        $payload['sort_order'] ??= (int) $homepageSection->bannerSlides()->max('sort_order') + 1;

        $slide = $homepageSection->bannerSlides()->create($payload);

        return (new HomepageBannerSlideResource($slide))->response()->setStatusCode(201);
    }

    public function update(UpdateBannerSlideRequest $request, HomepageSection $homepageSection, HomepageBannerSlide $slide): HomepageBannerSlideResource
    {
        abort_unless($slide->homepage_section_id === $homepageSection->id, 404);

        $slide->update($request->validated());

        return new HomepageBannerSlideResource($slide->fresh());
    }

    public function destroy(HomepageSection $homepageSection, HomepageBannerSlide $slide): JsonResponse
    {
        $this->authorize('update', $homepageSection);
        abort_unless($slide->homepage_section_id === $homepageSection->id, 404);

        $slide->delete();

        return response()->json(null, 204);
    }

    public function reorder(ReorderBannerSlideRequest $request, HomepageSection $homepageSection): JsonResponse
    {
        abort_unless($homepageSection->type === 'banner', 404);

        DB::transaction(function () use ($request, $homepageSection) {
            foreach ($request->validated()['order'] as $row) {
                HomepageBannerSlide::where('id', $row['id'])
                    ->where('homepage_section_id', $homepageSection->id) // double-scope guard
                    ->update(['sort_order' => $row['sort_order']]);
            }
        });

        \Illuminate\Support\Facades\Cache::forget(HomepageSection::CACHE_KEY);

        return response()->json(['message' => 'Reordered.']);
    }
}
