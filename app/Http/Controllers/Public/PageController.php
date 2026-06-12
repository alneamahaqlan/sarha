<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\StaticPage;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class PageController extends Controller
{
    /**
     * Render a published static page by slug (About, Privacy, Terms, ...).
     * The resolved row is cached per slug and busted on save by the model.
     */
    public function show(string $slug): View
    {
        $page = Cache::remember(StaticPage::CACHE_PREFIX . $slug, 3600, function () use ($slug) {
            return StaticPage::published()->where('slug', $slug)->first();
        });

        abort_if($page === null, 404);

        return view('public.pages.show', compact('page'));
    }
}
