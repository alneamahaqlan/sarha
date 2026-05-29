<?php

namespace App\Http\Middleware;

use App\Services\UserActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lightweight heartbeat for authenticated web users. Touches the
 * UserVisitSession row on every request the user makes so the
 * profile screen's "total sessions / time on platform / last login"
 * cards have real data.
 *
 * Mounted on the `web` group ABOVE other middleware so it runs even
 * when downstream middleware throws. Failures are swallowed inside
 * the logger so a broken sessions table never takes the site down.
 *
 * Skips:
 *  - non-authenticated requests (no user to attribute to)
 *  - any request that isn't a normal page navigation (no point
 *    extending the visit for a sendBeacon click tracker)
 */
class TrackUserVisit
{
    public function __construct(private UserActivityLogger $logger) {}

    public function handle(Request $request, Closure $next): Response
    {
        $userId = auth('web')->id();
        if ($userId && $this->shouldTrack($request)) {
            // Fire-and-forget — never propagate failures.
            try { $this->logger->touchVisitSession($request, $userId); }
            catch (\Throwable $e) { /* swallowed inside logger */ }
        }

        return $next($request);
    }

    private function shouldTrack(Request $request): bool
    {
        // Beacons / click trackers are too frequent to count as
        // visit activity — they'd inflate session counts on a single
        // page visit.
        if ($request->is('track/*')) return false;
        // Asset requests slip through sometimes via subdomain rules.
        $path = $request->path();
        if (str_starts_with($path, 'build/') || str_starts_with($path, 'storage/')) {
            return false;
        }
        return true;
    }
}
