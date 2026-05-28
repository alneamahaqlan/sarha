<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clinic\ReorderRequest;
use App\Http\Requests\Api\V1\Clinic\StoreServiceRequest;
use App\Http\Requests\Api\V1\Clinic\UpdateServiceRequest;
use App\Http\Resources\Api\V1\ServiceResource as ServiceApiResource;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ServiceController extends Controller
{
    private function clinicId(): int
    {
        return (int) auth('clinic')->id();
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Service::class);

        $query = Service::query()
            ->with('categories:id,name,name_en,slug,emoji')
            ->where('clinic_id', $this->clinicId());

        if ($search = $request->string('search')->toString()) {
            $query->where('name', 'like', "%{$search}%");
        }
        if (! is_null($request->input('filter.is_active'))) {
            $query->where('is_active', $request->boolean('filter.is_active'));
        }

        $sort = $request->string('sort', 'sort_order')->toString();
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $allowed = ['sort_order', 'name', 'price', 'is_active', 'created_at'];
        $query->orderBy(in_array($column, $allowed, true) ? $column : 'sort_order', $direction);

        $perPage = min(max((int) $request->input('per_page', 30), 1), 100);

        return ServiceApiResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['clinic_id'] = $this->clinicId();
        $categoryIds = $data['category_ids'] ?? [];
        unset($data['category_ids']);

        $service = Service::create($data);
        $service->categories()->sync($categoryIds);

        return (new ServiceApiResource($service->load('categories:id,name,name_en,slug,emoji')))
            ->response()->setStatusCode(201);
    }

    public function update(UpdateServiceRequest $request, Service $service): ServiceApiResource
    {
        // clinic_id never changes; ownership enforced in the request's authorize().
        $data = $request->validated();
        $categoryIds = $data['category_ids'] ?? null;
        unset($data['category_ids']);

        $service->update($data);
        if ($categoryIds !== null) {
            $service->categories()->sync($categoryIds);
        }

        return new ServiceApiResource($service->fresh()->load('categories:id,name,name_en,slug,emoji'));
    }

    public function destroy(Service $service): JsonResponse
    {
        $this->authorize('delete', $service);

        $service->delete();

        return response()->json(null, 204);
    }

    public function reorder(ReorderRequest $request): JsonResponse
    {
        $clinicId = $this->clinicId();

        DB::transaction(function () use ($request, $clinicId) {
            foreach ($request->validated()['order'] as $row) {
                // Scope every update to the authenticated clinic to prevent cross-clinic writes.
                Service::where('id', $row['id'])
                    ->where('clinic_id', $clinicId)
                    ->update(['sort_order' => $row['sort_order']]);
            }
        });

        return response()->json(['message' => 'Reordered.']);
    }
}
