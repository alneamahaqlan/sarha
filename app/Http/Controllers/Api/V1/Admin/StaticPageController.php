<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ReorderStaticPageRequest;
use App\Http\Requests\Api\V1\Admin\StoreStaticPageRequest;
use App\Http\Requests\Api\V1\Admin\UpdateStaticPageRequest;
use App\Http\Resources\Api\V1\StaticPageResource;
use App\Models\StaticPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class StaticPageController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', StaticPage::class);

        $query = StaticPage::query();

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('title_ar', 'like', "%{$search}%")
                  ->orWhere('title_en', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if (! is_null($request->input('filter.is_active'))) {
            $query->where('is_active', $request->boolean('filter.is_active'));
        }

        $sort = $request->string('sort', 'sort_order')->toString();
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $allowed = ['sort_order', 'title_ar', 'title_en', 'slug', 'is_active', 'created_at'];
        if (in_array($column, $allowed, true)) {
            $query->orderBy($column, $direction);
        } else {
            $query->orderBy('sort_order');
        }

        $perPage = min(max((int) $request->input('per_page', 30), 1), 100);

        return StaticPageResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function show(StaticPage $staticPage): StaticPageResource
    {
        $this->authorize('view', $staticPage);

        return new StaticPageResource($staticPage);
    }

    public function store(StoreStaticPageRequest $request): JsonResponse
    {
        $page = StaticPage::create($request->validated());

        return (new StaticPageResource($page))->response()->setStatusCode(201);
    }

    public function update(UpdateStaticPageRequest $request, StaticPage $staticPage): StaticPageResource
    {
        $staticPage->update($request->validated());

        return new StaticPageResource($staticPage);
    }

    public function destroy(StaticPage $staticPage): JsonResponse
    {
        $this->authorize('delete', $staticPage);

        // Core platform pages are protected — the policy already blocks this,
        // but we keep an explicit guard so the message is clear.
        if ($staticPage->is_system) {
            return response()->json([
                'message' => __('admin.validation.static_page_is_system'),
            ], 403);
        }

        $staticPage->delete();

        return response()->json(null, 204);
    }

    public function reorder(ReorderStaticPageRequest $request): JsonResponse
    {
        DB::transaction(function () use ($request) {
            foreach ($request->validated()['order'] as $row) {
                StaticPage::where('id', $row['id'])->update(['sort_order' => $row['sort_order']]);
            }
        });

        return response()->json(['message' => 'Reordered.']);
    }
}
