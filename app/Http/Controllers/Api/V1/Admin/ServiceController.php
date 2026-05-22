<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreServiceRequest;
use App\Http\Requests\Api\V1\Admin\UpdateServiceRequest;
use App\Http\Resources\Api\V1\ServiceResource as ServiceApiResource;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Service::class);

        $query = Service::query()->with('clinic:id,name');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('clinic', fn($qq) => $qq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($clinicId = $request->input('filter.clinic_id')) {
            $query->where('clinic_id', $clinicId);
        }
        if (! is_null($request->input('filter.is_active'))) {
            $query->where('is_active', $request->boolean('filter.is_active'));
        }

        $sort = $request->string('sort', '-created_at')->toString();
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $allowed = ['name', 'price', 'created_at', 'is_active', 'clinic_id'];
        if (in_array($column, $allowed, true)) {
            $query->orderBy($column, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return ServiceApiResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function show(Service $service): ServiceApiResource
    {
        $this->authorize('view', $service);

        return new ServiceApiResource($service->load('clinic:id,name'));
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $service = Service::create($request->validated());

        return (new ServiceApiResource($service->load('clinic:id,name')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateServiceRequest $request, Service $service): ServiceApiResource
    {
        $service->update($request->validated());

        return new ServiceApiResource($service->fresh()->load('clinic:id,name'));
    }

    public function destroy(Service $service): JsonResponse
    {
        $this->authorize('delete', $service);

        $service->delete();

        return response()->json(null, 204);
    }
}
