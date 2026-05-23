<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clinic\ReorderRequest;
use App\Http\Requests\Api\V1\Clinic\StoreCustomCategoryRequest;
use App\Http\Requests\Api\V1\Clinic\UpdateCustomCategoryRequest;
use App\Http\Resources\Api\V1\CustomCategoryResource as CustomCategoryApiResource;
use App\Models\CustomCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class CustomCategoryController extends Controller
{
    private function clinicId(): int
    {
        return (int) auth('clinic')->id();
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CustomCategory::class);

        $query = CustomCategory::query()
            ->where('clinic_id', $this->clinicId())
            ->withCount('services');

        if ($search = $request->string('search')->toString()) {
            $query->where('name', 'like', "%{$search}%");
        }

        $query->orderBy('sort_order');

        $perPage = min(max((int) $request->input('per_page', 50), 1), 100);

        return CustomCategoryApiResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function store(StoreCustomCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['clinic_id'] = $this->clinicId();

        $category = CustomCategory::create($data);

        return (new CustomCategoryApiResource($category->loadCount('services')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateCustomCategoryRequest $request, CustomCategory $customCategory): CustomCategoryApiResource
    {
        $customCategory->update($request->validated());

        return new CustomCategoryApiResource($customCategory->fresh()->loadCount('services'));
    }

    public function destroy(CustomCategory $customCategory): JsonResponse
    {
        $this->authorize('delete', $customCategory);

        $customCategory->delete();

        return response()->json(null, 204);
    }

    public function reorder(ReorderRequest $request): JsonResponse
    {
        $clinicId = $this->clinicId();

        DB::transaction(function () use ($request, $clinicId) {
            foreach ($request->validated()['order'] as $row) {
                CustomCategory::where('id', $row['id'])
                    ->where('clinic_id', $clinicId)
                    ->update(['sort_order' => $row['sort_order']]);
            }
        });

        return response()->json(['message' => 'Reordered.']);
    }
}
