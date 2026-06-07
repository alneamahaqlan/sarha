<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ReorderNavigationLinkRequest;
use App\Http\Requests\Api\V1\Admin\StoreNavigationLinkRequest;
use App\Http\Requests\Api\V1\Admin\UpdateNavigationLinkRequest;
use App\Http\Resources\Api\V1\NavigationLinkResource;
use App\Models\NavigationLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class NavigationLinkController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', NavigationLink::class);

        $query = NavigationLink::query()->with('staticPage');

        if ($location = $request->string('filter.location')->toString()) {
            $query->where('location', $location);
        }

        if (! is_null($request->input('filter.is_active'))) {
            $query->where('is_active', $request->boolean('filter.is_active'));
        }

        $query->orderBy('location')->orderBy('footer_column')->orderBy('sort_order');

        $perPage = min(max((int) $request->input('per_page', 100), 1), 200);

        return NavigationLinkResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function show(NavigationLink $navigationLink): NavigationLinkResource
    {
        $this->authorize('view', $navigationLink);

        return new NavigationLinkResource($navigationLink->load('staticPage'));
    }

    public function store(StoreNavigationLinkRequest $request): JsonResponse
    {
        $link = NavigationLink::create($request->validated());

        return (new NavigationLinkResource($link->load('staticPage')))->response()->setStatusCode(201);
    }

    public function update(UpdateNavigationLinkRequest $request, NavigationLink $navigationLink): NavigationLinkResource
    {
        $navigationLink->update($request->validated());

        return new NavigationLinkResource($navigationLink->load('staticPage'));
    }

    public function destroy(NavigationLink $navigationLink): JsonResponse
    {
        $this->authorize('delete', $navigationLink);

        $navigationLink->delete();

        return response()->json(null, 204);
    }

    public function reorder(ReorderNavigationLinkRequest $request): JsonResponse
    {
        DB::transaction(function () use ($request) {
            foreach ($request->validated()['order'] as $row) {
                NavigationLink::where('id', $row['id'])->update(['sort_order' => $row['sort_order']]);
            }
        });

        return response()->json(['message' => 'Reordered.']);
    }
}
