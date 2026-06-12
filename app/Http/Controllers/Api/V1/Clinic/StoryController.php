<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clinic\StoreStoryRequest;
use App\Http\Requests\Api\V1\Clinic\UpdateStoryRequest;
use App\Http\Resources\Api\V1\StoryResource;
use App\Models\Story;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Instagram-style stories managed by the authenticated complex. */
class StoryController extends Controller
{
    private function clinicId(): int
    {
        return (int) auth('clinic')->id();
    }

    private function authorizeOwnership(Story $story): void
    {
        abort_if($story->clinic_id !== $this->clinicId(), 404);
    }

    public function index(): AnonymousResourceCollection
    {
        $rows = Story::query()
            ->where('clinic_id', $this->clinicId())
            ->orderBy('sort_order')->orderByDesc('id')
            ->paginate(50);

        return StoryResource::collection($rows);
    }

    public function store(StoreStoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['clinic_id'] = $this->clinicId();

        $story = Story::create($data);

        return (new StoryResource($story))->response()->setStatusCode(201);
    }

    public function update(UpdateStoryRequest $request, Story $story): StoryResource
    {
        $this->authorizeOwnership($story);

        $story->update($request->validated());

        return new StoryResource($story->fresh());
    }

    public function destroy(Story $story): JsonResponse
    {
        $this->authorizeOwnership($story);

        $story->delete();

        return response()->json(null, 204);
    }
}
