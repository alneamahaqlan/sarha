<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\LandingPage;
use App\Models\LandingPageEvent;
use App\Models\LandingPageStat;
use App\Models\LandingPageVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Ingestion + lifecycle for landing-page behavioral tracking. All methods are
 * best-effort; callers wrap them in try/catch and the endpoints always 204.
 *
 * Privacy: only an allow-listed, non-health set of event payload keys is
 * stored, and the IP is hashed (never stored raw).
 */
class LandingPageTracker
{
    public const VISITOR_COOKIE = 'sarha_lv';

    /** Minutes of inactivity after which a new session row is started. */
    private const IDLE_MINUTES = 30;

    /** Event payload keys we are willing to persist (no health/service text). */
    private const ALLOWED_PAYLOAD_KEYS = ['depth', 'seconds', 'button', 'index', 'block'];

    /** Event types the ingestion endpoint will accept. */
    private const ALLOWED_EVENT_TYPES = [
        'view', 'scroll', 'dwell', 'click', 'whatsapp', 'call',
        'gallery', 'video', 'form_start', 'form_submit', 'registration',
    ];

    /**
     * Start (or resume, within the idle window) the visitor's session and
     * record the first view. Returns the visit id so the client can stitch
     * subsequent events to it.
     */
    public function startOrResumeVisit(Request $request): ?LandingPageVisit
    {
        $page = $this->resolvePage($request);
        if (! $page) {
            return null;
        }

        $visitorId = $this->visitorId($request);
        $sessionId = (string) ($request->input('session_id') ?: Str::uuid());

        $visit = LandingPageVisit::where('landing_page_id', $page->id)
            ->where('visitor_id', $visitorId)
            ->where('last_activity_at', '>=', now()->subMinutes(self::IDLE_MINUTES))
            ->latest('started_at')
            ->first();

        $isNewVisitor = ! LandingPageVisit::where('landing_page_id', $page->id)
            ->where('visitor_id', $visitorId)
            ->whereDate('started_at', today())
            ->exists();

        if (! $visit) {
            $visit = LandingPageVisit::create([
                'landing_page_id'  => $page->id,
                'visitor_id'       => $visitorId,
                'session_id'       => $sessionId,
                'user_id'          => auth('web')->id(),
                'utm_source'       => $request->input('utm_source'),
                'utm_medium'       => $request->input('utm_medium'),
                'utm_campaign'     => $request->input('utm_campaign'),
                'utm_content'      => $request->input('utm_content'),
                'utm_term'         => $request->input('utm_term'),
                'referrer'         => Str::limit((string) $request->input('referrer'), 250, ''),
                'landing_url'      => Str::limit((string) $request->input('landing_url'), 250, ''),
                'user_agent'       => Str::limit((string) $request->userAgent(), 250, ''),
                'device'           => $this->device($request),
                'ip_hash'          => $request->ip() ? hash('sha256', $request->ip() . config('app.key')) : null,
                'started_at'       => now(),
                'last_activity_at' => now(),
            ]);

            LandingPageStat::bump($page->id, 'visits');
            LandingPageStat::bump($page->id, 'page_views');
            if ($isNewVisitor) {
                LandingPageStat::bump($page->id, 'unique_visitors');
            }
        } else {
            $visit->forceFill(['last_activity_at' => now()])->save();
        }

        return $visit;
    }

    /** Append a batch of behavioral events to an open visit. */
    public function recordEvents(Request $request): void
    {
        $visit = $this->resolveVisit($request);
        if (! $visit) {
            return;
        }

        $events = $request->input('events', []);
        if (! is_array($events) || empty($events)) {
            return;
        }

        $rows = [];
        $now = now();
        $maxScroll = $visit->scroll_depth;
        $eventCount = 0;

        foreach (array_slice($events, 0, 50) as $e) {
            $type = $e['type'] ?? null;
            if (! in_array($type, self::ALLOWED_EVENT_TYPES, true)) {
                continue;
            }

            $payload = array_intersect_key(
                is_array($e['payload'] ?? null) ? $e['payload'] : [],
                array_flip(self::ALLOWED_PAYLOAD_KEYS),
            );

            $rows[] = [
                'landing_page_visit_id' => $visit->id,
                'landing_page_id'       => $visit->landing_page_id,
                'type'                  => $type,
                'payload'               => $payload ? json_encode($payload) : null,
                'occurred_at'           => $now,
                'created_at'            => $now,
            ];
            $eventCount++;

            if ($type === 'scroll' && isset($payload['depth'])) {
                $maxScroll = max($maxScroll, min(100, (int) $payload['depth']));
            }
            if ($type === 'click')    { LandingPageStat::bump($visit->landing_page_id, 'clicks'); }
            if ($type === 'whatsapp') { LandingPageStat::bump($visit->landing_page_id, 'whatsapp_clicks'); }
            if ($type === 'call')     { LandingPageStat::bump($visit->landing_page_id, 'calls'); }
        }

        if ($rows) {
            LandingPageEvent::insert($rows);
        }

        // A 2nd meaningful event (or a scroll past the fold) means it's not a bounce.
        $patch = ['last_activity_at' => $now, 'scroll_depth' => $maxScroll];
        if ($visit->is_bounce && ($eventCount >= 1 && ($maxScroll >= 25 || $visit->events()->count() > 1))) {
            $patch['is_bounce'] = false;
        }
        $visit->forceFill($patch)->save();
    }

    /** Finalize a visit on page-leave: stamp duration + ended_at. */
    public function finalize(Request $request): void
    {
        $visit = $this->resolveVisit($request);
        if (! $visit) {
            return;
        }

        $duration = max(0, min(6 * 3600, (int) $request->input('duration_seconds', 0)));
        $scroll = max($visit->scroll_depth, min(100, (int) $request->input('scroll_depth', 0)));

        $wasOpen = $visit->ended_at === null;

        $visit->forceFill([
            'ended_at'         => now(),
            'last_activity_at' => now(),
            'duration_seconds' => $duration,
            'scroll_depth'     => $scroll,
        ])->save();

        if ($wasOpen) {
            LandingPageStat::bump($visit->landing_page_id, 'sum_duration_seconds', $duration);
            if ($visit->is_bounce) {
                LandingPageStat::bump($visit->landing_page_id, 'bounces');
            }
        }
    }

    /**
     * Stitch a freshly-created booking back to the visitor's open visit (if the
     * tracker cookie identifies one), marking it converted. Best-effort.
     */
    public function markConvertedForRequest(Request $request, LandingPage $page, Booking $booking): void
    {
        $visitorId = $request->cookie(self::VISITOR_COOKIE) ?: $request->input('visitor_id');
        if (! $visitorId) {
            return;
        }

        $visit = LandingPageVisit::where('landing_page_id', $page->id)
            ->where('visitor_id', $visitorId)
            ->latest('started_at')
            ->first();

        if ($visit) {
            $this->markConverted($visit, $booking);
        }
    }

    public function markConverted(LandingPageVisit $visit, Booking $booking): void
    {
        if ($visit->converted) {
            return;
        }

        $visit->forceFill([
            'converted'    => true,
            'converted_at' => now(),
            'is_bounce'    => false,
            'booking_id'   => $booking->id,
            'user_id'      => $visit->user_id ?? auth('web')->id(),
        ])->save();

        LandingPageStat::bump($visit->landing_page_id, 'conversions');
    }

    // ── helpers ──

    private function resolvePage(Request $request): ?LandingPage
    {
        $id = (int) $request->input('page_id');
        return $id ? LandingPage::publiclyLive()->find($id) : null;
    }

    private function resolveVisit(Request $request): ?LandingPageVisit
    {
        if ($id = (int) $request->input('visit_id')) {
            return LandingPageVisit::find($id);
        }
        return null;
    }

    private function visitorId(Request $request): string
    {
        return (string) ($request->cookie(self::VISITOR_COOKIE)
            ?: $request->input('visitor_id')
            ?: Str::uuid());
    }

    private function device(Request $request): string
    {
        $ua = strtolower((string) $request->userAgent());
        if (str_contains($ua, 'mobile')) return 'mobile';
        if (str_contains($ua, 'tablet') || str_contains($ua, 'ipad')) return 'tablet';
        return 'desktop';
    }
}
