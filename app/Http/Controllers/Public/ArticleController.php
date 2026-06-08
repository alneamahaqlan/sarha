<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Article;

class ArticleController extends Controller
{
    /**
     * Public blog index — paginated list of all published articles whose
     * clinic is publicly visible. Gives every article a reachable home
     * beyond the homepage's "latest" strip, and enables the "view all"
     * link on the homepage articles section (guarded by Route::has).
     */
    public function index()
    {
        $articles = Article::query()
            ->where('is_published', true)
            ->whereHas('clinic', fn ($q) => $q->publiclyVisible())
            ->with(['clinic:id,name,slug'])
            ->latest('published_at')
            ->latest()
            ->paginate(12);

        return view('public.articles-index', compact('articles'));
    }

    /**
     * Public single-article page. Only published articles whose clinic is
     * publicly visible are reachable; views are counted on each load.
     */
    public function show(string $slug)
    {
        $article = Article::where('slug', $slug)
            ->where('is_published', true)
            ->whereHas('clinic', fn ($q) => $q->publiclyVisible())
            ->with(['clinic.city'])
            ->firstOrFail();

        // Lightweight view counter (no unique-visitor tracking — mirrors clinic stats).
        $article->increment('views_count');

        $related = Article::where('clinic_id', $article->clinic_id)
            ->where('is_published', true)
            ->where('id', '!=', $article->id)
            ->latest('published_at')
            ->latest()
            ->limit(3)
            ->get(['id', 'title', 'slug', 'meta_description', 'body', 'cover_image', 'published_at']);

        return view('public.article', compact('article', 'related'));
    }
}
