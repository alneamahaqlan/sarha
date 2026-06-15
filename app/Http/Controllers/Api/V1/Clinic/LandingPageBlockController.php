<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LandingPageBlockResource;
use App\Models\LandingPage;
use App\Models\LandingPageBlock;
use App\Services\LandingPageBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Drag-and-drop block CRUD for a clinic-owned landing page. Reuses the shared
 * LandingPageBuilderService; ownership is asserted on every call instead of the
 * admin-only LandingPagePolicy.
 */
class LandingPageBlockController extends Controller
{
    public function __construct(private readonly LandingPageBuilderService $builder)
    {
    }

    private function ownedOrFail(LandingPage $page): void
    {
        abort_unless($page->owner_clinic_id === (int) auth('clinic')->id(), 404);
    }

    public function index(LandingPage $landingPage): AnonymousResourceCollection
    {
        $this->ownedOrFail($landingPage);

        return LandingPageBlockResource::collection($this->builder->forAdmin($landingPage->id));
    }

    public function store(Request $request, LandingPage $landingPage): JsonResponse
    {
        $this->ownedOrFail($landingPage);

        $data = $request->validate([
            'type'       => ['required', Rule::in(LandingPage::BLOCK_TYPES)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_visible' => ['nullable', 'boolean'],
            'config'     => ['nullable', 'array'],
        ]);

        $data['sort_order'] = $data['sort_order']
            ?? ((int) $landingPage->blocks()->max('sort_order') + 10);

        $data['config'] = $data['config']
            ?? (LandingPageBuilderService::DEFAULTS[$data['type']] ?? []);

        $block = $landingPage->blocks()->create($data);

        return (new LandingPageBlockResource($block))->response()->setStatusCode(201);
    }

    public function update(Request $request, LandingPage $landingPage, LandingPageBlock $block): LandingPageBlockResource
    {
        $this->ownedOrFail($landingPage);
        abort_unless($block->landing_page_id === $landingPage->id, 404);

        $data = $request->validate([
            'is_visible' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'config'     => ['nullable', 'array'],
        ]);

        $block->update($data);

        return new LandingPageBlockResource($block);
    }

    public function destroy(LandingPage $landingPage, LandingPageBlock $block): JsonResponse
    {
        $this->ownedOrFail($landingPage);
        abort_unless($block->landing_page_id === $landingPage->id, 404);

        $block->delete();

        return response()->json(null, 204);
    }

    public function reorder(Request $request, LandingPage $landingPage): JsonResponse
    {
        $this->ownedOrFail($landingPage);

        $data = $request->validate([
            'order'              => ['required', 'array', 'min:1'],
            'order.*.id'         => ['required', 'integer', 'exists:landing_page_blocks,id'],
            'order.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $this->builder->reorder($landingPage, $data['order']);

        return response()->json(['message' => 'Reordered.']);
    }
}
