<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreCityRequest;
use App\Http\Requests\Api\V1\Admin\UpdateCityRequest;
use App\Http\Resources\Api\V1\CityResource;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CityController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', City::class);

        $query = City::query()->withCount('clinics');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('name_en', 'like', "%{$search}%");
            });
        }

        if (! is_null($request->input('filter.is_active'))) {
            $query->where('is_active', $request->boolean('filter.is_active'));
        }

        $sort = $request->string('sort', 'sort_order')->toString();
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $allowedSorts = ['sort_order', 'name', 'name_en', 'is_active', 'created_at', 'clinics_count'];
        if (in_array($column, $allowedSorts, true)) {
            $query->orderBy($column, $direction);
        } else {
            $query->orderBy('sort_order');
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return CityResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function show(City $city): CityResource
    {
        $this->authorize('view', $city);

        return new CityResource($city->loadCount('clinics'));
    }

    public function store(StoreCityRequest $request): JsonResponse
    {
        $city = City::create($request->validated());

        return (new CityResource($city->loadCount('clinics')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateCityRequest $request, City $city): CityResource
    {
        $city->update($request->validated());

        return new CityResource($city->loadCount('clinics'));
    }

    public function destroy(Request $request, City $city): JsonResponse
    {
        $this->authorize('delete', $city);

        // Mirror Filament's delete guard: a city tied to clinics cannot be removed.
        if ($city->clinics()->exists()) {
            return response()->json([
                'message' => __('admin.validation.city_has_clinics'),
            ], 403);
        }

        $city->delete();

        return response()->json(null, 204);
    }
}
