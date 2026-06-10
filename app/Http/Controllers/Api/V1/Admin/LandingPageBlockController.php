<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ReorderLandingPageBlockRequest;
use App\Http\Requests\Api\V1\Admin\StoreLandingPageBlockRequest;
use App\Http\Requests\Api\V1\Admin\UpdateLandingPageBlockRequest;
use App\Http\Resources\Api\V1\LandingPageBlockResource;
use App\Models\LandingPage;
use App\Models\LandingPageBlock;
use App\Services\LandingPageBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LandingPageBlockController extends Controller
{
    public function __construct(private readonly LandingPageBuilderService $builder)
    {
    }

    public function index(LandingPage $landingPage): AnonymousResourceCollection
    {
        $this->authorize('view', $landingPage);

        return LandingPageBlockResource::collection($this->builder->forAdmin($landingPage->id));
    }

    public function store(StoreLandingPageBlockRequest $request, LandingPage $landingPage): JsonResponse
    {
        $data = $request->validated();

        // New blocks land at the end of the current order unless told otherwise.
        $data['sort_order'] = $data['sort_order']
            ?? ((int) $landingPage->blocks()->max('sort_order') + 10);

        // Fall back to the per-type default config so the block opens sensible.
        $data['config'] = $data['config']
            ?? (LandingPageBuilderService::DEFAULTS[$data['type']] ?? []);

        $block = $landingPage->blocks()->create($data);

        return (new LandingPageBlockResource($block))->response()->setStatusCode(201);
    }

    public function update(UpdateLandingPageBlockRequest $request, LandingPage $landingPage, LandingPageBlock $block): LandingPageBlockResource
    {
        abort_unless($block->landing_page_id === $landingPage->id, 404);

        $block->update($request->validated());

        return new LandingPageBlockResource($block);
    }

    public function destroy(LandingPage $landingPage, LandingPageBlock $block): JsonResponse
    {
        $this->authorize('update', $landingPage);
        abort_unless($block->landing_page_id === $landingPage->id, 404);

        $block->delete();

        return response()->json(null, 204);
    }

    public function reorder(ReorderLandingPageBlockRequest $request, LandingPage $landingPage): JsonResponse
    {
        $this->builder->reorder($landingPage, $request->validated()['order']);

        return response()->json(['message' => 'Reordered.']);
    }
}
