<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clinic\GenerateArticleAiRequest;
use App\Http\Requests\Api\V1\Clinic\StoreArticleRequest;
use App\Http\Requests\Api\V1\Clinic\UpdateArticleRequest;
use App\Http\Resources\Api\V1\ArticleResource as ArticleApiResource;
use App\Models\Article;
use App\Observers\ArticleObserver;
use App\Services\AiContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ArticleController extends Controller
{
    private function clinicId(): int
    {
        return (int) auth('clinic')->id();
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Article::class);

        $query = Article::query()->where('clinic_id', $this->clinicId());

        if ($search = $request->string('search')->toString()) {
            $query->where('title', 'like', "%{$search}%");
        }

        $query->orderBy('created_at', 'desc');

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return ArticleApiResource::collection($query->paginate($perPage)->withQueryString())
            ->additional(['usage' => $this->monthlyUsage()]);
    }

    /**
     * Monthly publish-quota usage for the article counter widget. Mirrors
     * ArticleObserver's basic-plan limit (premium is unlimited → limit null).
     */
    private function monthlyUsage(): array
    {
        $clinic = auth('clinic')->user();
        $isPremium = $clinic->subscription_type === 'premium';

        $publishedThisMonth = Article::where('clinic_id', $this->clinicId())
            ->where('is_published', true)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return [
            'published_this_month' => $publishedThisMonth,
            'monthly_limit'        => $isPremium ? null : ArticleObserver::BASIC_MONTHLY_LIMIT,
            'is_premium'           => $isPremium,
        ];
    }

    public function show(Article $article): ArticleApiResource
    {
        $this->authorize('view', $article);

        return new ArticleApiResource($article);
    }

    public function store(StoreArticleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['clinic_id'] = $this->clinicId();

        // ArticleObserver enforces the basic-plan monthly publish limit on creating().
        // It may force is_published=false and push a notification — preserved as-is.
        $article = Article::create($data);

        return (new ArticleApiResource($article->fresh()))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateArticleRequest $request, Article $article): ArticleApiResource
    {
        // ArticleObserver::updating re-checks the limit on draft→published transitions.
        $article->update($request->validated());

        return new ArticleApiResource($article->fresh());
    }

    public function destroy(Article $article): JsonResponse
    {
        $this->authorize('delete', $article);

        $article->delete();

        return response()->json(null, 204);
    }

    /**
     * Single endpoint for AI text generation — mirrors the Clinic ArticleResource
     * ai_excerpt and ai_article hint actions. Routes through AiContentService
     * which picks the active provider (Gemini / OpenAI / Anthropic).
     */
    public function generateAi(GenerateArticleAiRequest $request, AiContentService $ai): JsonResponse
    {
        if (! $ai->isConfigured()) {
            return response()->json([
                'message' => __('admin.ai.not_configured'),
            ], 422);
        }

        $clinic = auth('clinic')->user();
        $title = $request->validated('title');

        try {
            $text = $request->validated('kind') === 'excerpt'
                ? $ai->generateExcerpt($title, $clinic->name ?? '')
                : $ai->generateArticle($title, $clinic->name ?? '');
        } catch (\Throwable $e) {
            return response()->json([
                'message' => __('admin.ai.failed'),
                'error'   => $e->getMessage(),
            ], 502);
        }

        return response()->json(['data' => ['text' => $text]]);
    }
}
