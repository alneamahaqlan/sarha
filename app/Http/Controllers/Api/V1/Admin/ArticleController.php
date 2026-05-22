<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreArticleRequest;
use App\Http\Requests\Api\V1\Admin\UpdateArticleRequest;
use App\Http\Resources\Api\V1\ArticleResource as ArticleApiResource;
use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ArticleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Article::query()->with('clinic:id,name');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhereHas('clinic', fn ($qq) => $qq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($clinicId = $request->input('filter.clinic_id')) {
            $query->where('clinic_id', $clinicId);
        }

        if (! is_null($request->input('filter.is_published'))) {
            $query->where('is_published', $request->boolean('filter.is_published'));
        }

        $sort = $request->string('sort', '-created_at')->toString();
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $allowed = ['title', 'created_at', 'views_count', 'is_published'];
        if (in_array($column, $allowed, true)) {
            $query->orderBy($column, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return ArticleApiResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function show(Article $article): ArticleApiResource
    {
        return new ArticleApiResource($article->load('clinic:id,name'));
    }

    public function store(StoreArticleRequest $request): JsonResponse
    {
        // ArticleObserver::creating still enforces the clinic's plan publish-limit.
        $article = Article::create($request->validated());

        return (new ArticleApiResource($article->fresh()->load('clinic:id,name')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateArticleRequest $request, Article $article): ArticleApiResource
    {
        // ArticleObserver::updating re-checks the limit on draft→published transitions.
        $article->update($request->validated());

        return new ArticleApiResource($article->fresh()->load('clinic:id,name'));
    }

    public function destroy(Article $article): JsonResponse
    {
        $article->delete();

        return response()->json(null, 204);
    }
}
