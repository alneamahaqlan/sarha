<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreServiceCategoryRequest;
use App\Http\Requests\Api\V1\Admin\UpdateServiceCategoryRequest;
use App\Http\Resources\Api\V1\ServiceCategoryResource;
use App\Models\ServiceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Admin CRUD for the service_categories lookup. Mirrors the existing
 * CategoryController shape (search, sort, paginate, reorder, delete guard)
 * so the React admin can reuse the same UX patterns.
 */
class ServiceCategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ServiceCategory::class);

        $query = ServiceCategory::query()->withCount('services');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('name_en', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if (! is_null($request->input('filter.is_active'))) {
            $query->where('is_active', $request->boolean('filter.is_active'));
        }

        $sort = $request->string('sort', 'sort_order')->toString();
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $allowed = ['sort_order', 'name', 'name_en', 'slug', 'is_active', 'created_at', 'services_count'];
        $query->orderBy(in_array($column, $allowed, true) ? $column : 'sort_order', $direction);

        $perPage = min(max((int) $request->input('per_page', 25), 1), 100);

        return ServiceCategoryResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function show(ServiceCategory $serviceCategory): ServiceCategoryResource
    {
        $this->authorize('view', $serviceCategory);

        return new ServiceCategoryResource($serviceCategory->loadCount('services'));
    }

    public function store(StoreServiceCategoryRequest $request): JsonResponse
    {
        $cat = ServiceCategory::create($request->validated());

        return (new ServiceCategoryResource($cat->loadCount('services')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateServiceCategoryRequest $request, ServiceCategory $serviceCategory): ServiceCategoryResource
    {
        $serviceCategory->update($request->validated());

        return new ServiceCategoryResource($serviceCategory->loadCount('services'));
    }

    public function destroy(ServiceCategory $serviceCategory): JsonResponse
    {
        $this->authorize('delete', $serviceCategory);

        // Mirror the categories delete guard: a service-category linked to
        // services cannot be removed. The DB has restrictOnDelete on top,
        // but we surface a 403 here for a clean UI message.
        if ($serviceCategory->services()->exists()) {
            return response()->json([
                'message' => __('admin.validation.service_category_has_services'),
            ], 403);
        }

        $serviceCategory->delete();

        return response()->json(null, 204);
    }

    public function reorder(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceCategory::class);

        $payload = $request->validate([
            'order'              => ['required', 'array', 'min:1'],
            'order.*.id'         => ['required', 'integer', 'exists:service_categories,id'],
            'order.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($payload) {
            foreach ($payload['order'] as $row) {
                ServiceCategory::where('id', $row['id'])->update(['sort_order' => $row['sort_order']]);
            }
        });

        return response()->json(['message' => 'Reordered.']);
    }
}
