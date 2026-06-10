<?php

namespace App\Services;

use App\Models\LandingPage;
use App\Models\LandingPageVisit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates landing-page analytics for the admin Analytics tab. Reads the
 * daily landing_page_stats rollup for the headline counters and trend, and
 * groups visits on utm_source on the fly for the traffic-source breakdown.
 */
class LandingPageStatsService
{
    public function compute(LandingPage $page, ?string $from = null, ?string $to = null): array
    {
        $to = $to ? Carbon::parse($to)->endOfDay() : Carbon::now()->endOfDay();
        $from = $from ? Carbon::parse($from)->startOfDay() : Carbon::now()->subDays(29)->startOfDay();

        $rows = $page->stats()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get();

        $sum = fn (string $f) => (int) $rows->sum($f);

        $pageViews   = $sum('page_views');
        $uniques     = $sum('unique_visitors');
        $visits      = $sum('visits');
        $bounces     = $sum('bounces');
        $conversions = $sum('conversions');
        $bookings    = $sum('bookings_count');
        $duration    = (int) $rows->sum('sum_duration_seconds');

        $convRate = $uniques > 0 ? round($conversions / $uniques * 100, 1) : 0.0;
        $bounceRate = $visits > 0 ? round($bounces / $visits * 100, 1) : 0.0;
        $avgSession = $visits > 0 ? (int) round($duration / $visits) : 0;

        // Daily trend for the chart.
        $trend = $rows->map(fn ($r) => [
            'date'        => (string) $r->date->toDateString(),
            'page_views'  => (int) $r->page_views,
            'unique'      => (int) $r->unique_visitors,
            'conversions' => (int) $r->conversions,
        ])->values();

        return [
            'totals' => [
                'page_views'      => $pageViews,
                'unique_visitors' => $uniques,
                'clicks'          => $sum('clicks'),
                'whatsapp_clicks' => $sum('whatsapp_clicks'),
                'calls'           => $sum('calls'),
                'bookings'        => $bookings,
                'conversions'     => $conversions,
                'conversion_rate' => $convRate,
                'bounce_rate'     => $bounceRate,
                'avg_session_sec' => $avgSession,
            ],
            'trend'   => $trend,
            'sources' => $this->trafficSources($page, $from, $to),
        ];
    }

    /** Traffic-source breakdown by utm_source (null → "direct"). */
    private function trafficSources(LandingPage $page, Carbon $from, Carbon $to): array
    {
        return LandingPageVisit::where('landing_page_id', $page->id)
            ->whereBetween('started_at', [$from, $to])
            ->select(DB::raw("COALESCE(NULLIF(utm_source, ''), 'direct') as source"), DB::raw('COUNT(*) as total'))
            ->groupBy('source')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => ['source' => $r->source, 'total' => (int) $r->total])
            ->all();
    }
}
