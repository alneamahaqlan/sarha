<?php

namespace App\Console\Commands;

use App\Models\LandingPageStat;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Self-heals the daily landing_page_stats rollup from the source visits/events
 * for a given day (default: yesterday). Live ingestion bumps these counters in
 * real time, but durations/bounces/conversions can drift if a leave beacon is
 * lost — this recomputes them authoritatively overnight.
 */
class RollupLandingStats extends Command
{
    protected $signature = 'saerha:rollup-landing-stats {--date= : YYYY-MM-DD (defaults to yesterday)}';

    protected $description = 'Recompute the daily landing-page analytics rollup from visits/events';

    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->toDateString()
            : Carbon::yesterday()->toDateString();

        $start = Carbon::parse($date)->startOfDay();
        $end = Carbon::parse($date)->endOfDay();

        // Per-page aggregates from the visit rows that started that day.
        $visitAgg = DB::table('landing_page_visits')
            ->whereBetween('started_at', [$start, $end])
            ->groupBy('landing_page_id')
            ->select(
                'landing_page_id',
                DB::raw('COUNT(*) as visits'),
                DB::raw('COUNT(DISTINCT visitor_id) as unique_visitors'),
                DB::raw('SUM(CASE WHEN is_bounce = 1 THEN 1 ELSE 0 END) as bounces'),
                DB::raw('SUM(duration_seconds) as sum_duration_seconds'),
                DB::raw('SUM(CASE WHEN converted = 1 THEN 1 ELSE 0 END) as conversions'),
                DB::raw('SUM(CASE WHEN booking_id IS NOT NULL THEN 1 ELSE 0 END) as bookings_count'),
            )
            ->get();

        // Per-page event-type counts for that day.
        $eventAgg = DB::table('landing_page_events')
            ->whereBetween('occurred_at', [$start, $end])
            ->groupBy('landing_page_id')
            ->select(
                'landing_page_id',
                DB::raw("SUM(CASE WHEN type = 'view' THEN 1 ELSE 0 END) as page_views"),
                DB::raw("SUM(CASE WHEN type = 'click' THEN 1 ELSE 0 END) as clicks"),
                DB::raw("SUM(CASE WHEN type = 'whatsapp' THEN 1 ELSE 0 END) as whatsapp_clicks"),
                DB::raw("SUM(CASE WHEN type = 'call' THEN 1 ELSE 0 END) as calls"),
            )
            ->get()
            ->keyBy('landing_page_id');

        $count = 0;
        foreach ($visitAgg as $v) {
            $e = $eventAgg->get($v->landing_page_id);
            LandingPageStat::updateOrCreate(
                ['landing_page_id' => $v->landing_page_id, 'date' => $date],
                [
                    // page_views = the visit-level views (one per visit start) is the
                    // authoritative count; event 'view' rows are a superset, so we
                    // keep the visit count for page_views and use events for the rest.
                    'page_views'           => (int) $v->visits,
                    'unique_visitors'      => (int) $v->unique_visitors,
                    'visits'               => (int) $v->visits,
                    'bounces'              => (int) $v->bounces,
                    'sum_duration_seconds' => (int) $v->sum_duration_seconds,
                    'conversions'          => (int) $v->conversions,
                    'bookings_count'       => (int) $v->bookings_count,
                    'clicks'               => (int) ($e->clicks ?? 0),
                    'whatsapp_clicks'      => (int) ($e->whatsapp_clicks ?? 0),
                    'calls'                => (int) ($e->calls ?? 0),
                ],
            );
            $count++;
        }

        $this->info("Rolled up landing stats for {$date}: {$count} page(s).");

        return self::SUCCESS;
    }
}
