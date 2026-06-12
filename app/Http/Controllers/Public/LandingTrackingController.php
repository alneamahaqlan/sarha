<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\LandingPageTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cookie;

/**
 * First-party behavioral tracking beacons for landing pages. CSRF-excluded
 * (see bootstrap/app.php) and throttled. Every handler is best-effort and
 * returns 204 so a tracking failure never affects the visitor.
 *
 * Phase 3 ships safe no-op-ish endpoints; Phase 4 wires the full
 * visit/event/leave ingestion in LandingPageTracker.
 */
class LandingTrackingController extends Controller
{
    public function __construct(private readonly LandingPageTracker $tracker)
    {
    }

    public function visit(Request $request): JsonResponse
    {
        $visitId = null;
        $visitorId = null;
        try {
            $visit = $this->tracker->startOrResumeVisit($request);
            $visitId = $visit?->id;
            $visitorId = $visit?->visitor_id;
        } catch (\Throwable) {
            // swallow
        }

        $response = response()->json(['visit_id' => $visitId, 'visitor_id' => $visitorId]);

        // Persist the anonymous visitor id for 1 year so unique-visitor counts
        // and conversion stitching survive across sessions.
        if ($visitorId) {
            $response->withCookie(Cookie::make(LandingPageTracker::VISITOR_COOKIE, $visitorId, 60 * 24 * 365));
        }

        return $response;
    }

    public function event(Request $request): Response
    {
        try {
            $this->tracker->recordEvents($request);
        } catch (\Throwable) {
            // swallow
        }

        return response()->noContent();
    }

    public function leave(Request $request): Response
    {
        try {
            $this->tracker->finalize($request);
        } catch (\Throwable) {
            // swallow
        }

        return response()->noContent();
    }
}
