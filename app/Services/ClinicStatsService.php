<?php

namespace App\Services;

use App\Enums\ImpressionSource;
use App\Models\Article;
use App\Models\Booking;
use App\Models\Clinic;
use App\Models\ClinicStat;
use App\Models\PriceQuoteRequest;
use App\Models\Service;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Computes the full statistics payload for a single clinic over a date range.
 * Shared by the clinic "My stats" page and the admin per-clinic stats view, so
 * both render identical data. A clinic only ever sees its own numbers; the
 * platform comparison aggregates are cached for 5 minutes.
 */
class ClinicStatsService
{
    private const CACHE_TTL = 300; // 5 minutes
    private const BOOKING_STATUSES = ['new', 'contacted', 'appointment_set', 'completed', 'no_show', 'cancelled'];
    private const QUOTE_STATUSES = ['new', 'replied', 'closed'];

    /**
     * Resolve a date range from request input. Prefers an explicit from/to
     * custom range (swapped if reversed, clamped to today, capped at 366 days);
     * otherwise falls back to a quick period (7/14/30/90), default 30.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function resolveRange(?string $from, ?string $to, int $period = 0): array
    {
        $today = Carbon::today();

        if ($from && $to) {
            try {
                $f = Carbon::parse($from)->startOfDay();
                $t = Carbon::parse($to)->startOfDay();

                if ($t->lt($f)) {
                    [$f, $t] = [$t, $f];
                }
                if ($t->gt($today)) {
                    $t = $today->copy();
                }
                if ($f->diffInDays($t) > 366) {
                    $f = $t->copy()->subDays(366);
                }

                return [$f, $t];
            } catch (\Throwable $e) {
                // fall through to period default
            }
        }

        $n = in_array($period, [7, 14, 30, 90], true) ? $period : 30;

        return [$today->copy()->subDays($n - 1), $today->copy()];
    }

    /**
     * @param bool $showAi When true, the impressions breakdown surfaces
     *                     the AI source as its own row. When false (the
     *                     default — clinic-facing path), AI's count is
     *                     folded into the total but NOT enumerated, so
     *                     the clinic can't tell which impressions came
     *                     from the assistant. The dashboard total ≠ sum
     *                     of the displayed rows by design.
     */
    public function compute(Clinic $clinic, Carbon $from, Carbon $to, bool $showAi = false): array
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();
        $startAt = $from->copy()->startOfDay();
        $endAt = $to->copy()->endOfDay();

        $scoped = ClinicStat::where('clinic_id', $clinic->id)->whereBetween('date', [$fromDate, $toDate]);

        $t = (clone $scoped)->selectRaw(
            'COALESCE(SUM(page_views),0) pv, '
            . 'COALESCE(SUM(bookings_count),0) bk, COALESCE(SUM(quote_requests_count),0) qr, '
            . 'COALESCE(SUM(whatsapp_clicks),0) wa, COALESCE(SUM(call_clicks),0) cl, '
            . 'COALESCE(SUM(booking_clicks),0) bc, COALESCE(SUM(directions_clicks),0) dr'
        )->first();

        $pv = (int) $t->pv;
        $bk = (int) $t->bk;
        $qr = (int) $t->qr;

        // Multi-source impressions — replaces the legacy single
        // search_appearances column with a per-source breakdown.
        $impressions = $this->impressionBreakdown($clinic->id, $fromDate, $toDate, $showAi);
        $impressionsTotal = $impressions['total'];

        $summary = [
            // Legacy alias — kept so existing back-compat consumers
            // (e.g. comparison rank) keep working; equal to the
            // ALL-source total, including AI when present.
            'search_appearances' => $impressionsTotal,
            'impressions_total'  => $impressionsTotal,
            'impressions'        => $impressions['by_source'],
            'page_views'         => $pv,
            'bookings'           => $bk,
            'quote_requests'     => $qr,
            'whatsapp_clicks'    => (int) $t->wa,
            'call_clicks'        => (int) $t->cl,
            'directions_clicks'  => (int) $t->dr,
            'booking_clicks'     => (int) $t->bc,
            'conversion_rate'    => $pv > 0 ? round((($bk + $qr) / $pv) * 100, 1) : 0.0,
        ];

        // Trend now sources its impressions-per-day from the new
        // breakdown table; everything else (page_views, bookings,
        // quotes) stays on clinic_stats.
        $trend = $this->trend($clinic->id, $fromDate, $toDate);

        $comparison = $this->comparison($clinic, $fromDate, $toDate, $bk, $pv, $impressionsTotal);

        // ---- bookings & quotes distributions ----
        $bookings = Booking::where('clinic_id', $clinic->id)->whereBetween('created_at', [$startAt, $endAt]);
        $quotes = PriceQuoteRequest::where('clinic_id', $clinic->id)->whereBetween('created_at', [$startAt, $endAt]);

        $bookingsByStatus = $this->fillStatuses(
            (clone $bookings)->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status'),
            self::BOOKING_STATUSES
        );
        $bookingsBySource = (clone $bookings)->selectRaw('source, COUNT(*) c')->groupBy('source')
            ->pluck('c', 'source')->map(fn ($v) => (int) $v);

        $quotesByStatus = $this->fillStatuses(
            (clone $quotes)->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status'),
            self::QUOTE_STATUSES
        );
        $quotesTopServices = (clone $quotes)->selectRaw('service_name, COUNT(*) c')
            ->groupBy('service_name')->orderByDesc('c')->limit(3)->get()
            ->map(fn ($r) => ['name' => $r->service_name, 'count' => (int) $r->c])->values();

        // ---- services performance ----
        $bookingsByService = (clone $bookings)->whereNotNull('service_id')
            ->selectRaw('service_id, COUNT(*) c')->groupBy('service_id')->pluck('c', 'service_id');

        $servicesPerf = Service::where('clinic_id', $clinic->id)->where('is_active', true)
            ->get(['id', 'name', 'price'])
            ->map(fn (Service $s) => [
                'id'             => $s->id,
                'name'           => $s->name,
                'price'          => (float) $s->price,
                'bookings'       => (int) ($bookingsByService[$s->id] ?? 0),
                'quote_requests' => (int) (clone $quotes)->where('service_name', $s->name)->count(),
            ])
            ->sortByDesc('bookings')->values();

        // ---- articles performance ----
        $articles = Article::where('clinic_id', $clinic->id)->where('is_published', true)
            ->orderByDesc('views_count')->limit(20)
            ->get(['id', 'title', 'slug', 'views_count', 'ai_generated'])
            ->map(fn (Article $a) => [
                'id'           => $a->id,
                'title'        => $a->title,
                'slug'         => $a->slug,
                'views'        => (int) $a->views_count,
                'ai_generated' => (bool) $a->ai_generated,
            ])->values();

        // Top services BY IMPRESSIONS with per-source breakdown — new
        // table the spec asks for. Same showAi gating as the clinic
        // summary so the clinic-facing call hides the AI column.
        $topServices = $this->topServicesByImpressions($clinic->id, $fromDate, $toDate, $showAi);

        return [
            'period'                => ['from' => $fromDate, 'to' => $toDate, 'days' => $from->diffInDays($to) + 1],
            'summary'               => $summary,
            'comparison'            => $comparison,
            'trend'                 => $trend,
            'bookings_by_status'    => $bookingsByStatus,
            'bookings_by_source'    => $bookingsBySource,
            'quotes_by_status'      => $quotesByStatus,
            'quotes_top_services'   => $quotesTopServices,
            'services_performance'  => $servicesPerf,
            'top_services_by_views' => $topServices,
            'articles_performance'  => $articles,
            'best_days'             => $this->bestDays($clinic, $fromDate, $toDate),
            'recommendation'        => $this->recommendation($summary, $comparison),
            // Back-compat keys still read by the current admin stats page.
            'cards' => ['page_views' => $pv, 'bookings' => $bk, 'quote_requests' => $qr, 'search_appearances' => $impressionsTotal],
            'kpis'  => ['this_clinic_bookings' => $bk, 'avg_bookings_platform' => $comparison['avg_bookings']],
        ];
    }

    private function comparison(Clinic $clinic, string $from, string $to, int $bookings, int $visits, int $appearances): array
    {
        // Rank active complexes by total impressions (all sources, AI
        // included — the ranking is a private platform-level metric).
        // Cache key bumped to v3 so the post-impressions-migration
        // ranking can't be served stale.
        $ordered = Cache::remember("stats:rankmap:v3:{$from}:{$to}", self::CACHE_TTL, function () use ($from, $to) {
            $active = Clinic::where('status', 'active')->pluck('id');
            $sums = DB::table('clinic_impressions')
                ->whereIn('clinic_id', $active)
                ->whereBetween('date', [$from, $to])
                ->selectRaw('clinic_id, SUM(count) a')
                ->groupBy('clinic_id')
                ->pluck('a', 'clinic_id');

            return $active->mapWithKeys(fn ($id) => [$id => (int) ($sums[$id] ?? 0)])
                ->sortDesc()->keys()->values()->all();
        });

        $averages = Cache::remember("stats:avg:v3:{$from}:{$to}", self::CACHE_TTL, function () use ($from, $to) {
            $active = Clinic::where('status', 'active')->pluck('id');
            $count = max($active->count(), 1);
            $agg = ClinicStat::whereIn('clinic_id', $active)->whereBetween('date', [$from, $to])
                ->selectRaw('COALESCE(SUM(bookings_count),0) bk, COALESCE(SUM(page_views),0) pv')
                ->first();
            $impressionSum = (int) DB::table('clinic_impressions')
                ->whereIn('clinic_id', $active)
                ->whereBetween('date', [$from, $to])
                ->sum('count');

            return [
                'avg_bookings'    => round(((int) $agg->bk) / $count, 1),
                'avg_visits'      => round(((int) $agg->pv) / $count, 1),
                'avg_appearances' => round($impressionSum / $count, 1),
            ];
        });

        $pos = array_search($clinic->id, $ordered, true);

        return [
            'rank'                   => $pos === false ? null : $pos + 1,
            'total'                  => count($ordered),
            'clinic_bookings'        => $bookings,
            'avg_bookings'           => (float) $averages['avg_bookings'],
            'clinic_visits'          => $visits,
            'avg_visits'             => (float) $averages['avg_visits'],
            'clinic_appearances'     => $appearances,
            'avg_appearances'        => (float) $averages['avg_appearances'],
            'bookings_vs_avg_pct'    => $averages['avg_bookings'] > 0
                ? (int) round((($bookings - $averages['avg_bookings']) / $averages['avg_bookings']) * 100) : null,
            'visits_vs_avg_pct'      => $averages['avg_visits'] > 0
                ? (int) round((($visits - $averages['avg_visits']) / $averages['avg_visits']) * 100) : null,
            'appearances_vs_avg_pct' => $averages['avg_appearances'] > 0
                ? (int) round((($appearances - $averages['avg_appearances']) / $averages['avg_appearances']) * 100) : null,
        ];
    }

    private function bestDays(Clinic $clinic, string $from, string $to): array
    {
        $rows = ClinicStat::where('clinic_id', $clinic->id)->whereBetween('date', [$from, $to])
            ->get(['date', 'page_views', 'bookings_count']);

        $visits = array_fill(0, 7, 0);
        $requests = array_fill(0, 7, 0);
        foreach ($rows as $r) {
            $w = (int) ($r->date?->dayOfWeek ?? 0); // 0 = Sunday
            $visits[$w] += (int) $r->page_views;
            $requests[$w] += (int) $r->bookings_count;
        }

        return [
            'top_visits_weekday'   => array_sum($visits) > 0 ? (int) array_keys($visits, max($visits))[0] : null,
            'top_requests_weekday' => array_sum($requests) > 0 ? (int) array_keys($requests, max($requests))[0] : null,
        ];
    }

    /**
     * Picks one recommendation key; the frontend renders the localized message
     * (it already has the comparison numbers for interpolation).
     */
    private function recommendation(array $s, array $c): string
    {
        if ($c['rank'] !== null && $c['rank'] <= 5) {
            return 'top_rank';
        }
        if ($s['page_views'] === 0 && $s['search_appearances'] > 0) {
            return 'no_visits';
        }
        if ($s['page_views'] >= 20 && $s['conversion_rate'] < 5) {
            return 'low_conversion';
        }
        if ($s['booking_clicks'] > 0 && $s['bookings'] === 0) {
            return 'clicks_no_submit';
        }
        if ($s['page_views'] === 0) {
            return 'no_data';
        }

        return 'keep_going';
    }

    /** @param \Illuminate\Support\Collection<string,int> $counts */
    private function fillStatuses($counts, array $statuses): array
    {
        $out = [];
        foreach ($statuses as $status) {
            $out[$status] = (int) ($counts[$status] ?? 0);
        }

        return $out;
    }

    /**
     * Trailing visibility summary for one clinic over a date range, sourced
     * through the SAME path as the "My stats" page: impressions come from
     * clinic_impressions via impressionBreakdown(), the rest from clinic_stats.
     *
     * Lets lighter surfaces (the clinic dashboard card) show an impressions
     * number guaranteed identical to the stats page without running the full
     * compute() payload. See TC-001 (impressions discrepancy).
     *
     * @return array{impressions:int, page_views:int, bookings:int, quote_requests:int}
     */
    public function visibility(int $clinicId, string $fromDate, string $toDate): array
    {
        $t = ClinicStat::where('clinic_id', $clinicId)
            ->whereBetween('date', [$fromDate, $toDate])
            ->selectRaw(
                'COALESCE(SUM(page_views),0) pv, '
                . 'COALESCE(SUM(bookings_count),0) bk, '
                . 'COALESCE(SUM(quote_requests_count),0) qr'
            )->first();

        // total always sums EVERY source (AI included) — same number the
        // stats page headline shows; showAi only affects the per-row split.
        $impressions = $this->impressionBreakdown($clinicId, $fromDate, $toDate, false);

        return [
            'impressions'    => $impressions['total'],
            'page_views'     => (int) $t->pv,
            'bookings'       => (int) $t->bk,
            'quote_requests' => (int) $t->qr,
        ];
    }

    /**
     * Per-source impression breakdown for one clinic over the date range.
     *
     * Returns:
     *   - total: ALL sources, AI included (so the dashboard headline
     *            number always reflects every impression)
     *   - by_source: keyed by source string, values are int counts. When
     *                $showAi is false, the AI row is OMITTED from
     *                by_source — the spec is explicit that the clinic
     *                shouldn't see AI named, just feel its effect via
     *                the total > sum-of-rows.
     */
    private function impressionBreakdown(int $clinicId, string $fromDate, string $toDate, bool $showAi): array
    {
        $rows = DB::table('clinic_impressions')
            ->where('clinic_id', $clinicId)
            ->whereBetween('date', [$fromDate, $toDate])
            ->selectRaw('source, COALESCE(SUM(count), 0) AS c')
            ->groupBy('source')
            ->pluck('c', 'source');

        $bySource = [];
        $total = 0;
        $allowed = $showAi ? ImpressionSource::ALL : ImpressionSource::CLINIC_VISIBLE;

        // Initialise every allowed bucket to 0 so the UI doesn't have
        // to guess which sources exist.
        foreach ($allowed as $source) {
            $bySource[$source] = 0;
        }

        foreach ($rows as $source => $count) {
            $count = (int) $count;
            $total += $count; // total always sums EVERYTHING
            if (in_array($source, $allowed, true)) {
                $bySource[$source] = $count;
            }
            // sources outside $allowed (i.e. AI when !$showAi) flow
            // into $total but never appear in $bySource.
        }

        return ['total' => $total, 'by_source' => $bySource];
    }

    /**
     * Per-day total impressions (across ALL sources, AI included) plus
     * the legacy page_views/bookings/quotes that the chart already
     * graphs. Single query for impressions, plus existing clinic_stats.
     */
    private function trend(int $clinicId, string $fromDate, string $toDate): array
    {
        $impressionsByDate = DB::table('clinic_impressions')
            ->where('clinic_id', $clinicId)
            ->whereBetween('date', [$fromDate, $toDate])
            ->selectRaw('date, COALESCE(SUM(count), 0) AS c')
            ->groupBy('date')
            ->pluck('c', 'date');

        return ClinicStat::where('clinic_id', $clinicId)
            ->whereBetween('date', [$fromDate, $toDate])
            ->orderBy('date')
            ->get(['date', 'page_views', 'bookings_count', 'quote_requests_count'])
            ->map(fn (ClinicStat $s) => [
                'date'               => $s->date?->toDateString(),
                'search_appearances' => (int) ($impressionsByDate[$s->date?->toDateString()] ?? 0),
                'page_views'         => (int) $s->page_views,
                'bookings'           => (int) $s->bookings_count,
                'quote_requests'     => (int) $s->quote_requests_count,
            ])->values()->all();
    }

    /**
     * Top services for this clinic by impression count over the range.
     * Each row carries a per-source breakdown so the React table can
     * render the same "Total + 5/6 sources" pattern the summary uses.
     */
    private function topServicesByImpressions(int $clinicId, string $fromDate, string $toDate, bool $showAi): array
    {
        $allowed = $showAi ? ImpressionSource::ALL : ImpressionSource::CLINIC_VISIBLE;

        // Pull EVERY source so we can compute the (visible) total
        // including AI even when the UI omits the AI column.
        $rows = DB::table('service_impressions as si')
            ->join('services as s', 's.id', '=', 'si.service_id')
            ->where('si.clinic_id', $clinicId)
            ->whereBetween('si.date', [$fromDate, $toDate])
            ->selectRaw('si.service_id, s.name, si.source, SUM(si.count) AS c')
            ->groupBy('si.service_id', 's.name', 'si.source')
            ->get();

        $buckets = [];
        foreach ($rows as $r) {
            $sid = (int) $r->service_id;
            if (! isset($buckets[$sid])) {
                $buckets[$sid] = [
                    'service_id' => $sid,
                    'name'       => $r->name,
                    'total'      => 0,
                    'by_source'  => array_fill_keys($allowed, 0),
                ];
            }
            $count = (int) $r->c;
            $buckets[$sid]['total'] += $count;
            if (in_array($r->source, $allowed, true)) {
                $buckets[$sid]['by_source'][$r->source] = $count;
            }
        }

        // Sort by total desc, cap at top 20 — same threshold as
        // articles_performance.
        usort($buckets, fn ($a, $b) => $b['total'] <=> $a['total']);
        return array_slice($buckets, 0, 20);
    }
}
