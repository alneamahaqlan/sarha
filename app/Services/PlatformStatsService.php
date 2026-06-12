<?php

namespace App\Services;

use App\Enums\ImpressionSource;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Clinic;
use App\Models\ClinicStat;
use App\Models\PriceQuoteRequest;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Platform-wide analytics aggregated across every clinic for a date range.
 *
 * The admin analog of ClinicStatsService: it speaks the same metric
 * vocabulary (impressions + per-source breakdown, page views, conversions,
 * trend, status distributions, best days, top services) but summed over the
 * whole platform instead of a single clinic. On top of that it adds the
 * admin-only roll-ups a data analyst actually needs: revenue and subscription
 * growth, clinic growth, an acquisition funnel, a top-clinics leaderboard,
 * and period-over-period deltas against the immediately preceding window.
 *
 * Impressions are read from the new clinic_impressions / service_impressions
 * tables so the numbers reconcile with the per-clinic stats page (see TC-001).
 */
class PlatformStatsService
{
    private const BOOKING_STATUSES = ['new', 'contacted', 'appointment_set', 'completed', 'no_show', 'cancelled'];
    private const QUOTE_STATUSES = ['new', 'replied', 'closed'];

    public function compute(Carbon $from, Carbon $to): array
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();
        $startAt = $from->copy()->startOfDay();
        $endAt = $to->copy()->endOfDay();
        $days = $from->diffInDays($to) + 1;

        // ---- clinic_stats roll-up over the whole platform ----
        $t = ClinicStat::whereBetween('date', [$fromDate, $toDate])->selectRaw(
            'COALESCE(SUM(page_views),0) pv, COALESCE(SUM(bookings_count),0) bk, '
            . 'COALESCE(SUM(quote_requests_count),0) qr, COALESCE(SUM(whatsapp_clicks),0) wa, '
            . 'COALESCE(SUM(call_clicks),0) cl, COALESCE(SUM(booking_clicks),0) bc, '
            . 'COALESCE(SUM(directions_clicks),0) dr'
        )->first();

        $pv = (int) $t->pv;
        $bk = (int) $t->bk;
        $qr = (int) $t->qr;

        // ---- impressions, all 6 sources (AI included — admin surface) ----
        $impressionRows = DB::table('clinic_impressions')
            ->whereBetween('date', [$fromDate, $toDate])
            ->selectRaw('source, COALESCE(SUM(count),0) c')
            ->groupBy('source')
            ->pluck('c', 'source');

        $bySource = [];
        $impressionsTotal = 0;
        foreach (ImpressionSource::ALL as $src) {
            $val = (int) ($impressionRows[$src] ?? 0);
            $bySource[$src] = $val;
            $impressionsTotal += $val;
        }
        // Any source outside the canonical list still counts toward the total.
        foreach ($impressionRows as $src => $c) {
            if (! in_array($src, ImpressionSource::ALL, true)) {
                $impressionsTotal += (int) $c;
            }
        }

        // Unique viewers ("المشاهدات") platform-wide: distinct visitors
        // de-duplicated per 3-hour window, summed across every clinic. Each
        // clinic_views row is already one unique visitor per window.
        $uniqueViews = (int) DB::table('clinic_views')
            ->whereBetween('date', [$fromDate, $toDate])
            ->count();

        $contacts = (int) $t->wa + (int) $t->cl + (int) $t->bc;
        $conversions = $bk + $qr;

        $summary = [
            'impressions_total' => $impressionsTotal,
            'impressions'       => $bySource,
            'unique_views'      => $uniqueViews,
            'page_views'        => $pv,
            'bookings'          => $bk,
            'quote_requests'    => $qr,
            'whatsapp_clicks'   => (int) $t->wa,
            'call_clicks'       => (int) $t->cl,
            'directions_clicks' => (int) $t->dr,
            'booking_clicks'    => (int) $t->bc,
            'conversion_rate'   => $pv > 0 ? round(($conversions / $pv) * 100, 1) : 0.0,
            'engagement_rate'   => $impressionsTotal > 0 ? round(($pv / $impressionsTotal) * 100, 1) : 0.0,
        ];

        // Realized service revenue across all clinics: net price of services
        // taken on COMPLETED bookings in the range. Distinct from subscription
        // revenue (the platform's own income).
        $serviceRevenue = $this->serviceRevenue($startAt, $endAt);

        // ---- revenue + platform growth within the range ----
        $platform = [
            'revenue' => (float) Subscription::where('status', 'active')
                ->whereBetween('created_at', [$startAt, $endAt])->sum('amount'),
            'service_revenue' => $serviceRevenue,
            'new_subscriptions' => (int) Subscription::whereBetween('created_at', [$startAt, $endAt])->count(),
            'active_subscriptions' => (int) Subscription::where('status', 'active')
                ->where('ends_at', '>=', now())->count(),
            'new_clinics'   => (int) Clinic::whereBetween('created_at', [$startAt, $endAt])->count(),
            'active_clinics' => (int) Clinic::where('status', 'active')->count(),
            'total_clinics' => (int) Clinic::count(),
            // Cumulative platform-wide tallies (all-time), mirrored on each
            // clinic header + stats page. followers = clinic_follows rows,
            // customers = unified customer rows (booked + CRM-imported),
            // bookings = every booking ever placed.
            'total_followers' => (int) DB::table('clinic_follows')->count(),
            'total_customers' => (int) DB::table('customers')->count(),
            'total_bookings'  => (int) Booking::count(),
        ];

        // ---- period-over-period deltas vs the immediately preceding window ----
        $prevTo = $from->copy()->subDay();
        $prevFrom = $prevTo->copy()->subDays($days - 1);
        $deltas = $this->deltas(
            $prevFrom->toDateString(), $prevTo->toDateString(),
            $prevFrom->copy()->startOfDay(), $prevTo->copy()->endOfDay(),
            $impressionsTotal, $pv, $bk, $qr, $platform['revenue'], $uniqueViews, $serviceRevenue
        );

        // ---- acquisition funnel: impressions → views → contacts → conversions ----
        $funnel = [
            'impressions'  => $impressionsTotal,
            'page_views'   => $pv,
            'contacts'     => $contacts,
            'conversions'  => $conversions,
            'view_rate'    => $impressionsTotal > 0 ? round(($pv / $impressionsTotal) * 100, 1) : 0.0,
            'contact_rate' => $pv > 0 ? round(($contacts / $pv) * 100, 1) : 0.0,
            'close_rate'   => $contacts > 0 ? round(($conversions / $contacts) * 100, 1) : 0.0,
        ];

        // ---- impressions by source, with share-of-total ----
        $impressionsBySource = [];
        foreach (ImpressionSource::ALL as $src) {
            $impressionsBySource[] = [
                'source' => $src,
                'count'  => $bySource[$src],
                'pct'    => $impressionsTotal > 0 ? round(($bySource[$src] / $impressionsTotal) * 100, 1) : 0.0,
            ];
        }

        return [
            'period'                => ['from' => $fromDate, 'to' => $toDate, 'days' => $days],
            'summary'               => $summary,
            'platform'              => $platform,
            'deltas'                => $deltas,
            'funnel'                => $funnel,
            'trend'                 => $this->trend($fromDate, $toDate, $from, $to),
            'impressions_by_source' => $impressionsBySource,
            'bookings_by_status'    => $this->distribution(
                Booking::whereBetween('created_at', [$startAt, $endAt]), self::BOOKING_STATUSES
            ),
            'quotes_by_status'      => $this->distribution(
                PriceQuoteRequest::whereBetween('created_at', [$startAt, $endAt]), self::QUOTE_STATUSES
            ),
            'bookings_by_source'    => Booking::whereBetween('created_at', [$startAt, $endAt])
                ->selectRaw('source, COUNT(*) c')->groupBy('source')
                ->pluck('c', 'source')->map(fn ($v) => (int) $v),
            'service_stats'         => $this->serviceStats($startAt, $endAt),
            'best_days'             => $this->bestDays($fromDate, $toDate),
            'top_clinics'           => $this->topClinics($fromDate, $toDate),
            'top_clinics_by_revenue'=> $this->topClinicsByRevenue($fromDate, $toDate),
            'top_services'          => $this->topServices($fromDate, $toDate),
            'by_specialty'          => $this->bySpecialty(),
            'monthly'               => $this->monthly(),
            'landing_pages'         => $this->landingPages($fromDate, $toDate, $startAt, $endAt, $from, $to),
        ];
    }

    /**
     * Platform-wide landing-page analytics for the range — the central-center
     * view of the per-page LandingPageStatsService. Reads landing_page_stats
     * (daily rollup) for the totals/trend and landing_page_visits for the
     * traffic-source breakdown, plus a top-pages leaderboard.
     */
    private function landingPages(string $fromDate, string $toDate, Carbon $startAt, Carbon $endAt, Carbon $from, Carbon $to): array
    {
        $t = DB::table('landing_page_stats')->whereBetween('date', [$fromDate, $toDate])->selectRaw(
            'COALESCE(SUM(page_views),0) pv, COALESCE(SUM(unique_visitors),0) uv, COALESCE(SUM(visits),0) vs, '
            . 'COALESCE(SUM(bookings_count),0) bk, COALESCE(SUM(conversions),0) cv, COALESCE(SUM(clicks),0) ck, '
            . 'COALESCE(SUM(whatsapp_clicks),0) wa, COALESCE(SUM(calls),0) cl, COALESCE(SUM(bounces),0) bo, '
            . 'COALESCE(SUM(sum_duration_seconds),0) dur'
        )->first();

        $visits = (int) $t->vs;
        $uniques = (int) $t->uv;
        $conversions = (int) $t->cv;
        $bounces = (int) $t->bo;
        $duration = (int) $t->dur;

        $summary = [
            'page_views'      => (int) $t->pv,
            'unique_visitors' => $uniques,
            'visits'          => $visits,
            'bookings'        => (int) $t->bk,
            'conversions'     => $conversions,
            'clicks'          => (int) $t->ck,
            'whatsapp_clicks' => (int) $t->wa,
            'calls'           => (int) $t->cl,
            'conversion_rate' => $uniques > 0 ? round($conversions / $uniques * 100, 1) : 0.0,
            'bounce_rate'     => $visits > 0 ? round($bounces / $visits * 100, 1) : 0.0,
            'avg_session_sec' => $visits > 0 ? (int) round($duration / $visits) : 0,
            'active_pages'    => (int) DB::table('landing_pages')->whereNull('deleted_at')->where('status', 'published')->count(),
        ];

        // Per-day trend, zero-filled across the window.
        $rows = DB::table('landing_page_stats')->whereBetween('date', [$fromDate, $toDate])
            ->selectRaw('date, COALESCE(SUM(page_views),0) pv, COALESCE(SUM(unique_visitors),0) uv, COALESCE(SUM(conversions),0) cv')
            ->groupBy('date')->get()->keyBy(fn ($r) => (string) $r->date);

        $trend = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $d = $cursor->toDateString();
            $row = $rows[$d] ?? null;
            $trend[] = [
                'date'            => $d,
                'page_views'      => (int) ($row->pv ?? 0),
                'unique_visitors' => (int) ($row->uv ?? 0),
                'conversions'     => (int) ($row->cv ?? 0),
            ];
            $cursor->addDay();
        }

        // Top landing pages by page views.
        $topAgg = DB::table('landing_page_stats')->whereBetween('date', [$fromDate, $toDate])
            ->selectRaw('landing_page_id, SUM(page_views) pv, SUM(unique_visitors) uv, SUM(conversions) cv')
            ->groupBy('landing_page_id')->orderByDesc('pv')->limit(15)->get();

        $meta = DB::table('landing_pages')->whereIn('id', $topAgg->pluck('landing_page_id'))
            ->get(['id', 'slug', 'title_ar', 'type'])->keyBy('id');

        $top = $topAgg->map(function ($r) use ($meta) {
            $m = $meta[$r->landing_page_id] ?? null;
            $uv = (int) $r->uv;
            $cv = (int) $r->cv;
            return [
                'id'              => (int) $r->landing_page_id,
                'slug'            => $m->slug ?? null,
                'title'           => $m->title_ar ?: ('#' . $r->landing_page_id),
                'type'            => $m->type ?? null,
                'page_views'      => (int) $r->pv,
                'unique_visitors' => $uv,
                'conversions'     => $cv,
                'conversion_rate' => $uv > 0 ? round($cv / $uv * 100, 1) : 0.0,
            ];
        })->all();

        // Traffic sources across all landing visits in the range (utm_source).
        $sources = DB::table('landing_page_visits')
            ->whereBetween('started_at', [$startAt, $endAt])
            ->selectRaw("COALESCE(NULLIF(utm_source, ''), 'direct') as source, COUNT(*) as total")
            ->groupBy('source')->orderByDesc('total')->limit(10)->get()
            ->map(fn ($r) => ['source' => $r->source, 'total' => (int) $r->total])->all();

        return ['summary' => $summary, 'trend' => $trend, 'top' => $top, 'sources' => $sources];
    }

    /**
     * Previous-window totals → percentage change for each headline metric.
     * Returns null for a metric whose previous value was 0 (no baseline).
     */
    private function deltas(string $pFrom, string $pTo, Carbon $pStart, Carbon $pEnd, int $imp, int $pv, int $bk, int $qr, float $rev, int $uv, float $svcRev): array
    {
        $s = ClinicStat::whereBetween('date', [$pFrom, $pTo])->selectRaw(
            'COALESCE(SUM(page_views),0) pv, COALESCE(SUM(bookings_count),0) bk, COALESCE(SUM(quote_requests_count),0) qr'
        )->first();
        $pImp = (int) DB::table('clinic_impressions')->whereBetween('date', [$pFrom, $pTo])->sum('count');
        $pUv = (int) DB::table('clinic_views')->whereBetween('date', [$pFrom, $pTo])->count();
        $pRev = (float) Subscription::where('status', 'active')->whereBetween('created_at', [$pStart, $pEnd])->sum('amount');
        $pSvcRev = $this->serviceRevenue($pStart, $pEnd);

        $pct = fn ($cur, $prev) => $prev > 0 ? (int) round((($cur - $prev) / $prev) * 100) : null;

        return [
            'impressions'     => $pct($imp, $pImp),
            'unique_views'    => $pct($uv, $pUv),
            'page_views'      => $pct($pv, (int) $s->pv),
            'bookings'        => $pct($bk, (int) $s->bk),
            'quote_requests'  => $pct($qr, (int) $s->qr),
            'revenue'         => $pct($rev, $pRev),
            'service_revenue' => $pct($svcRev, $pSvcRev),
        ];
    }

    /**
     * Realized service revenue across all clinics for a range: SUM of net
     * price on COMPLETED bookings. Basis = bookings.created_at.
     */
    private function serviceRevenue(Carbon $startAt, Carbon $endAt): float
    {
        return (float) DB::table('booking_services as bs')
            ->join('bookings as b', 'b.id', '=', 'bs.booking_id')
            ->where('b.status', Booking::STATUS_COMPLETED)
            ->whereNull('b.deleted_at')
            ->whereBetween('b.created_at', [$startAt, $endAt])
            ->sum('bs.net_price');
    }

    /**
     * Platform-wide service operations block for the range, all derived from
     * booking_services net price: realized income + services taken + people
     * served (completed), value still pending (open bookings), and value lost
     * (cancelled / no-show). Basis = bookings.created_at.
     */
    private function serviceStats(Carbon $startAt, Carbon $endAt): array
    {
        $base = fn () => DB::table('booking_services as bs')
            ->join('bookings as b', 'b.id', '=', 'bs.booking_id')
            ->whereNull('b.deleted_at')
            ->whereBetween('b.created_at', [$startAt, $endAt]);

        $completed = $base()->where('b.status', Booking::STATUS_COMPLETED)
            ->selectRaw('COUNT(*) c, COUNT(DISTINCT b.customer_id) cust, COALESCE(SUM(bs.net_price),0) v')->first();
        $lost = $base()->whereIn('b.status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
            ->selectRaw('COUNT(*) c, COALESCE(SUM(bs.net_price),0) v')->first();
        $pending = $base()->whereNotIn('b.status', [Booking::STATUS_COMPLETED, Booking::STATUS_NO_SHOW, Booking::STATUS_CANCELLED])
            ->selectRaw('COUNT(*) c, COALESCE(SUM(bs.net_price),0) v')->first();

        return [
            'income'           => (float) $completed->v,
            'services_taken'   => (int) $completed->c,
            'customers_served' => (int) $completed->cust,
            'lost_income'      => (float) $lost->v,
            'lost_services'    => (int) $lost->c,
            'pending_income'   => (float) $pending->v,
            'pending_services' => (int) $pending->c,
        ];
    }

    /**
     * Top 15 clinics by realized service revenue over the range, each with
     * its completed-booking count. Ranks by money, complementing the
     * impressions leaderboard.
     */
    private function topClinicsByRevenue(string $from, string $to): array
    {
        return DB::table('booking_services as bs')
            ->join('bookings as b', 'b.id', '=', 'bs.booking_id')
            ->join('clinics as c', 'c.id', '=', 'bs.clinic_id')
            ->where('b.status', Booking::STATUS_COMPLETED)
            ->whereNull('b.deleted_at')
            ->whereBetween('b.created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->selectRaw('bs.clinic_id, c.name, SUM(bs.net_price) revenue, COUNT(DISTINCT b.id) completed')
            ->groupBy('bs.clinic_id', 'c.name')
            ->orderByDesc('revenue')
            ->limit(15)
            ->get()
            ->map(fn ($r) => [
                'id'                 => (int) $r->clinic_id,
                'name'               => $r->name,
                'service_revenue'    => (float) $r->revenue,
                'completed_bookings' => (int) $r->completed,
            ])->all();
    }

    /**
     * Per-day series across the full range. Impressions come from
     * clinic_impressions; page views / bookings / quotes from clinic_stats.
     * Every day in the window is emitted, gaps filled with zeros.
     */
    private function trend(string $fromDate, string $toDate, Carbon $from, Carbon $to): array
    {
        $imp = DB::table('clinic_impressions')->whereBetween('date', [$fromDate, $toDate])
            ->selectRaw('date, COALESCE(SUM(count),0) c')->groupBy('date')->pluck('c', 'date');

        $uv = DB::table('clinic_views')->whereBetween('date', [$fromDate, $toDate])
            ->selectRaw('date, COUNT(*) c')->groupBy('date')->pluck('c', 'date');

        $stats = ClinicStat::whereBetween('date', [$fromDate, $toDate])
            ->selectRaw('date, COALESCE(SUM(page_views),0) pv, COALESCE(SUM(bookings_count),0) bk, COALESCE(SUM(quote_requests_count),0) qr')
            ->groupBy('date')->get()->keyBy(fn ($r) => $r->date->toDateString());

        $out = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $d = $cursor->toDateString();
            $row = $stats[$d] ?? null;
            $out[] = [
                'date'           => $d,
                'impressions'    => (int) ($imp[$d] ?? 0),
                'unique_views'   => (int) ($uv[$d] ?? 0),
                'page_views'     => (int) ($row->pv ?? 0),
                'bookings'       => (int) ($row->bk ?? 0),
                'quote_requests' => (int) ($row->qr ?? 0),
            ];
            $cursor->addDay();
        }

        return $out;
    }

    /** Status distribution for a scoped query, zero-filled to the known set. */
    private function distribution($query, array $statuses): array
    {
        $counts = (clone $query)->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');

        $out = [];
        foreach ($statuses as $status) {
            $out[$status] = (int) ($counts[$status] ?? 0);
        }

        return $out;
    }

    /**
     * Platform weekday totals for visits and requests. Weekday index matches
     * Carbon's dayOfWeek (0 = Sunday) so it shares the clinic UI's day labels.
     */
    private function bestDays(string $from, string $to): array
    {
        $rows = ClinicStat::whereBetween('date', [$from, $to])
            ->selectRaw('(DAYOFWEEK(date) - 1) AS w, COALESCE(SUM(page_views),0) pv, COALESCE(SUM(bookings_count),0) bk')
            ->groupBy('w')->get();

        $visits = array_fill(0, 7, 0);
        $requests = array_fill(0, 7, 0);
        foreach ($rows as $r) {
            $w = (int) $r->w;
            $visits[$w] = (int) $r->pv;
            $requests[$w] = (int) $r->bk;
        }

        return [
            'top_visits_weekday'   => array_sum($visits) > 0 ? (int) array_keys($visits, max($visits))[0] : null,
            'top_requests_weekday' => array_sum($requests) > 0 ? (int) array_keys($requests, max($requests))[0] : null,
        ];
    }

    /**
     * Top 15 clinics by impressions over the range, each with its visits,
     * conversions and conversion rate so the admin can spot high-traffic /
     * low-conversion outliers.
     */
    private function topClinics(string $from, string $to): array
    {
        $impr = DB::table('clinic_impressions')->whereBetween('date', [$from, $to])
            ->selectRaw('clinic_id, SUM(count) c')->groupBy('clinic_id')
            ->orderByDesc('c')->limit(15)->pluck('c', 'clinic_id');

        if ($impr->isEmpty()) {
            return [];
        }

        $ids = $impr->keys()->all();
        $stats = ClinicStat::whereIn('clinic_id', $ids)->whereBetween('date', [$from, $to])
            ->selectRaw('clinic_id, COALESCE(SUM(page_views),0) pv, COALESCE(SUM(bookings_count),0) bk, COALESCE(SUM(quote_requests_count),0) qr')
            ->groupBy('clinic_id')->get()->keyBy('clinic_id');
        $names = Clinic::whereIn('id', $ids)->pluck('name', 'id');

        $out = [];
        foreach ($impr as $cid => $cnt) {
            $st = $stats[$cid] ?? null;
            $pv = (int) ($st->pv ?? 0);
            $bk = (int) ($st->bk ?? 0);
            $qr = (int) ($st->qr ?? 0);
            $out[] = [
                'id'              => (int) $cid,
                'name'            => $names[$cid] ?? ('#' . $cid),
                'impressions'     => (int) $cnt,
                'page_views'      => $pv,
                'bookings'        => $bk,
                'quote_requests'  => $qr,
                'conversion_rate' => $pv > 0 ? round((($bk + $qr) / $pv) * 100, 1) : 0.0,
            ];
        }

        return $out;
    }

    /**
     * Top 20 services platform-wide by impression count, with the same
     * per-source breakdown the clinic stats page renders, plus the owning
     * clinic's name (admin needs to know whose service it is).
     */
    private function topServices(string $from, string $to): array
    {
        $rows = DB::table('service_impressions as si')
            ->join('services as s', 's.id', '=', 'si.service_id')
            ->join('clinics as c', 'c.id', '=', 'si.clinic_id')
            ->whereBetween('si.date', [$from, $to])
            ->selectRaw('si.service_id, s.name, c.name AS clinic_name, si.source, SUM(si.count) AS cnt')
            ->groupBy('si.service_id', 's.name', 'c.name', 'si.source')
            ->get();

        $buckets = [];
        foreach ($rows as $r) {
            $sid = (int) $r->service_id;
            if (! isset($buckets[$sid])) {
                $buckets[$sid] = [
                    'service_id'  => $sid,
                    'name'        => $r->name,
                    'clinic_name' => $r->clinic_name,
                    'total'       => 0,
                    'by_source'   => array_fill_keys(ImpressionSource::ALL, 0),
                ];
            }
            $count = (int) $r->cnt;
            $buckets[$sid]['total'] += $count;
            if (in_array($r->source, ImpressionSource::ALL, true)) {
                $buckets[$sid]['by_source'][$r->source] = $count;
            }
        }

        usort($buckets, fn ($a, $b) => $b['total'] <=> $a['total']);

        return array_slice(array_values($buckets), 0, 20);
    }

    /** Clinic count by specialty (top 8) — platform structure, range-independent. */
    private function bySpecialty(): array
    {
        return Category::query()
            ->withCount('clinics')
            ->orderByDesc('clinics_count')
            ->limit(8)
            ->get(['id', 'name', 'name_en'])
            ->map(fn (Category $c) => [
                'name'          => $c->display_name,
                'clinics_count' => (int) $c->clinics_count,
            ])->all();
    }

    /** Last 6 calendar months of clinic / booking / revenue growth. */
    private function monthly(): array
    {
        return collect(range(5, 0))->map(function (int $back) {
            $month = now()->subMonths($back);
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();

            return [
                'month'    => $month->format('Y-m'),
                'clinics'  => (int) Clinic::whereBetween('created_at', [$start, $end])->count(),
                'bookings' => (int) Booking::whereBetween('created_at', [$start, $end])->count(),
                'revenue'  => (float) Subscription::where('status', 'active')
                    ->whereBetween('created_at', [$start, $end])->sum('amount'),
                'service_revenue' => $this->serviceRevenue($start, $end),
            ];
        })->values()->all();
    }
}
