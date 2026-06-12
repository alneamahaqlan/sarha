<?php

namespace App\Http\Middleware;

use App\Services\ImpersonationService;
use App\Services\Tracking\TrackingContext;
use App\Services\Tracking\TrackingContextResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the per-request TrackingContext for public clinic pages and
 * binds it into the container for the tracking blade partials to read.
 *
 * Inert by default: only clinic-scoped public routes (those with a {slug}
 * parameter) get a context; everything else keeps the disabled default.
 * We never track admin/clinic-panel surfaces or impersonation sessions.
 */
class ResolveTrackingContext
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $context = $this->resolve($request);
            if ($context !== null) {
                app()->instance(TrackingContext::class, $context);
            }
        } catch (\Throwable $e) {
            // Tracking must never break a page render. Leave the disabled
            // default in place and move on.
            report($e);
        }

        return $next($request);
    }

    private function resolve(Request $request): ?TrackingContext
    {
        // Never track app shells, APIs, or panel surfaces.
        if ($request->is('app', 'app/*', 'api/*', 'admin', 'admin/*', 'clinic-dashboard', 'clinic-dashboard/*')) {
            return null;
        }

        // Never track while a super-admin is impersonating a clinic.
        if ($request->session()->has(ImpersonationService::SESSION_KEY)) {
            return null;
        }

        $route = $request->route();
        if ($route === null) {
            return null;
        }

        // Platform pixels load on every public page; clinic pixels merge in
        // when a {slug} is present. Passing a null slug yields platform-only.
        $slug = array_key_exists('slug', $route->parameters())
            ? (string) $route->parameter('slug')
            : null;

        // Booking form / confirmation expose a medical service in the URL —
        // flag them so the sent page_location is sanitised.
        $routeName = $route->getName() ?? '';
        $isSensitive = str_contains($routeName, 'book');

        return app(TrackingContextResolver::class)->forClinicSlug($slug, $isSensitive);
    }
}
