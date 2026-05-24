<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Clinic;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $clinics = Clinic::publiclyVisible()
            ->select(['slug', 'updated_at'])
            ->latest('updated_at')
            ->limit(5000)
            ->get();

        $urls = collect();

        // Static URLs
        foreach ([
            ['loc' => route('home'),   'priority' => '1.0', 'changefreq' => 'daily'],
            ['loc' => route('search'), 'priority' => '0.9', 'changefreq' => 'daily'],
            ['loc' => route('login'),  'priority' => '0.3', 'changefreq' => 'monthly'],
        ] as $entry) {
            $urls->push(array_merge($entry, ['lastmod' => now()->toIso8601String()]));
        }

        // Clinic pages
        foreach ($clinics as $clinic) {
            $urls->push([
                'loc'        => route('clinic.show', $clinic->slug),
                'priority'   => '0.8',
                'changefreq' => 'weekly',
                'lastmod'    => $clinic->updated_at?->toIso8601String() ?? now()->toIso8601String(),
            ]);
        }

        // Published articles of publicly-visible clinics
        $articles = Article::where('is_published', true)
            ->whereHas('clinic', fn ($q) => $q->publiclyVisible())
            ->select(['slug', 'updated_at'])
            ->latest('updated_at')
            ->limit(5000)
            ->get();

        foreach ($articles as $article) {
            $urls->push([
                'loc'        => route('article.show', $article->slug),
                'priority'   => '0.6',
                'changefreq' => 'monthly',
                'lastmod'    => $article->updated_at?->toIso8601String() ?? now()->toIso8601String(),
            ]);
        }

        $xml = view('sitemap', ['urls' => $urls])->render();

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    public function robots(): Response
    {
        $content = "User-agent: *\n"
            . "Allow: /\n"
            . "Disallow: /admin\n"
            . "Disallow: /clinic-dashboard\n"
            . "Disallow: /booking/\n"
            . "Disallow: /login\n"
            . "\n"
            . "Sitemap: " . url('/sitemap.xml') . "\n";

        return response($content, 200, ['Content-Type' => 'text/plain']);
    }
}
