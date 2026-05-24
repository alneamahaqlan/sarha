<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clinic\StoreBeforeAfterRequest;
use App\Http\Requests\Api\V1\Clinic\UpdateBeforeAfterRequest;
use App\Http\Resources\Api\V1\BeforeAfterPhotoResource;
use App\Models\BeforeAfterPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Before/after photo gallery managed by the authenticated complex. */
class BeforeAfterController extends Controller
{
    private function clinicId(): int
    {
        return (int) auth('clinic')->id();
    }

    private function authorizeOwnership(BeforeAfterPhoto $photo): void
    {
        abort_if($photo->clinic_id !== $this->clinicId(), 404);
    }

    public function index(): AnonymousResourceCollection
    {
        $rows = BeforeAfterPhoto::query()
            ->where('clinic_id', $this->clinicId())
            ->with(['subClinic:id,name', 'service:id,name'])
            ->orderBy('sort_order')->orderByDesc('id')
            ->paginate(50);

        return BeforeAfterPhotoResource::collection($rows);
    }

    public function store(StoreBeforeAfterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['clinic_id'] = $this->clinicId();

        $photo = BeforeAfterPhoto::create($data);

        return (new BeforeAfterPhotoResource($photo->load(['subClinic:id,name', 'service:id,name'])))
            ->response()->setStatusCode(201);
    }

    public function update(UpdateBeforeAfterRequest $request, BeforeAfterPhoto $beforeAfter): BeforeAfterPhotoResource
    {
        $this->authorizeOwnership($beforeAfter);

        $beforeAfter->update($request->validated());

        return new BeforeAfterPhotoResource($beforeAfter->fresh()->load(['subClinic:id,name', 'service:id,name']));
    }

    public function destroy(BeforeAfterPhoto $beforeAfter): JsonResponse
    {
        $this->authorizeOwnership($beforeAfter);

        $beforeAfter->delete();

        return response()->json(null, 204);
    }
}
