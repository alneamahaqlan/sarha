<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiAssistantLog;
use App\Models\Category;
use App\Models\Clinic;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Read-only analytics for the AI Center "Analytics" tab.
 *
 * One endpoint, one big JSON payload. Cached for 5 minutes — the
 * dashboard refreshes on tab switch and via query-cache invalidation
 * on writes (handled in Phase 1's seeders / Phase 2's job).
 *
 * Conversion rate is a heuristic: % of conversations where at least one
 * clinic surfaced in any of the thread's turns. NOT click-through —
 * documented so the dashboard doesn't oversell it.
 */
class AiCenterAnalyticsController extends Controller
{
    private const ALLOWED_RANGES = [7, 30, 90];

    public function show(Request $request): JsonResponse
    {
        $days = (int) $request->input('days', 30);
        if (! in_array($days, self::ALLOWED_RANGES, true)) {
            $days = 30;
        }

        $ttl = max(1, (int) SystemSetting::get('ai_analytics_cache_ttl', 300));
        $payload = Cache::remember(
            "ai_center:analytics:v1:{$days}",
            $ttl,
            fn () => $this->build($days),
        );

        return response()->json(['data' => $payload]);
    }

    private function build(int $days): array
    {
        $from = now()->subDays($days)->startOfDay();

        // Base scope used across every aggregate so they all agree on
        // the timeframe. A fresh `clone` per use protects each query.
        $base = AiAssistantLog::query()->where('created_at', '>=', $from);

        return [
            'range_days'      => $days,
            'computed_at'     => now()->toIso8601String(),
            'kpis'            => $this->kpis(clone $base, $days),
            'trend'           => $this->trend($from, $days),
            'top_topics'      => $this->topTopics(clone $base),
            'top_clinics'     => $this->topClinics($from),
            'top_categories'  => $this->topCategories($from),
            'kind_breakdown'  => $this->kindBreakdown(clone $base),
            'provider_perf'   => $this->providerPerformance(clone $base),
        ];
    }

    private function kpis($base, int $days): array
    {
        $total = (clone $base)->count();
        $uniqueUsers   = (clone $base)->whereNotNull('user_id')->distinct('user_id')->count('user_id');
        $uniqueVisitors = (clone $base)->whereNotNull('visitor_id')->distinct('visitor_id')->count('visitor_id');

        // Conversation count + avg length — heuristic via conversation_id
        // for rows that have one (Phase 1 seeder rows won't until
        // backfilled, but the backfill happens at the same time as the
        // migration).
        $conversationCount = (clone $base)->whereNotNull('conversation_id')->distinct('conversation_id')->count('conversation_id');
        $avgLength = $conversationCount > 0
            ? round($total / $conversationCount, 1)
            : 1.0;

        // Conversion proxy: conversations where ≥1 clinic surfaced.
        $convertedConversations = (clone $base)
            ->whereNotNull('conversation_id')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('ai_assistant_log_clinics')
                    ->whereColumn('ai_assistant_log_clinics.log_id', 'ai_assistant_logs.id');
            })
            ->distinct('conversation_id')
            ->count('conversation_id');
        $conversionRate = $conversationCount > 0
            ? round($convertedConversations / $conversationCount * 100, 1)
            : 0.0;

        $tokens = (clone $base)
            ->selectRaw('SUM(tokens_in) as tin, SUM(tokens_out) as tout')
            ->first();

        return [
            'conversations'     => $conversationCount,
            'turns'             => $total,
            'unique_users'      => $uniqueUsers + $uniqueVisitors,
            'avg_length_turns'  => $avgLength,
            'conversion_rate'   => $conversionRate,
            'tokens_in'         => (int) ($tokens->tin  ?? 0),
            'tokens_out'        => (int) ($tokens->tout ?? 0),
        ];
    }

    private function trend(Carbon $from, int $days): array
    {
        $rows = AiAssistantLog::query()
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->orderBy('d')
            ->pluck('c', 'd');

        // Fill missing days with 0 so the line chart doesn't skip.
        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = now()->subDays($i)->toDateString();
            $series[] = ['date' => $d, 'count' => (int) ($rows[$d] ?? 0)];
        }
        return $series;
    }

    /**
     * Top topics — derived from the first 8 words of the user query.
     * Naive but works against the seeded data; Phase 3 could swap this
     * for the LLM-generated topic on the summary table.
     */
    private function topTopics($base): array
    {
        $rows = (clone $base)
            ->where('was_blocked', false)
            ->where('was_emergency', false)
            ->selectRaw('SUBSTRING_INDEX(query, " ", 4) as topic, COUNT(*) as c')
            ->groupBy('topic')
            ->orderByDesc('c')
            ->limit(10)
            ->get();

        return $rows->map(fn ($r) => ['topic' => (string) $r->topic, 'count' => (int) $r->c])->values()->all();
    }

    private function topClinics(Carbon $from): array
    {
        $rows = DB::table('ai_assistant_log_clinics as p')
            ->join('ai_assistant_logs as l', 'l.id', '=', 'p.log_id')
            ->join('clinics as c', 'c.id', '=', 'p.clinic_id')
            ->where('l.created_at', '>=', $from)
            ->selectRaw('c.id, c.name, COUNT(*) as c')
            ->groupBy('c.id', 'c.name')
            ->orderByDesc('c')
            ->limit(10)
            ->get();

        return $rows->map(fn ($r) => ['id' => (int) $r->id, 'name' => $r->name, 'count' => (int) $r->c])->all();
    }

    private function topCategories(Carbon $from): array
    {
        $rows = DB::table('ai_assistant_log_categories as p')
            ->join('ai_assistant_logs as l', 'l.id', '=', 'p.log_id')
            ->join('categories as cat', 'cat.id', '=', 'p.category_id')
            ->where('l.created_at', '>=', $from)
            ->selectRaw('cat.id, COALESCE(cat.name_en, cat.name) as name, COUNT(*) as c')
            ->groupBy('cat.id', 'name')
            ->orderByDesc('c')
            ->limit(10)
            ->get();

        return $rows->map(fn ($r) => ['id' => (int) $r->id, 'name' => $r->name, 'count' => (int) $r->c])->all();
    }

    private function kindBreakdown($base): array
    {
        // Collapse the assistant's 8+ "kind" buckets into 3 the dashboard
        // pie cares about: normal / blocked / emergency.
        $rows = (clone $base)
            ->selectRaw("
                SUM(CASE WHEN was_blocked = 1 THEN 1 ELSE 0 END) as blocked,
                SUM(CASE WHEN was_emergency = 1 THEN 1 ELSE 0 END) as emergency,
                SUM(CASE WHEN was_blocked = 0 AND was_emergency = 0 THEN 1 ELSE 0 END) as normal
            ")
            ->first();

        return [
            ['kind' => 'normal',    'count' => (int) ($rows->normal    ?? 0)],
            ['kind' => 'blocked',   'count' => (int) ($rows->blocked   ?? 0)],
            ['kind' => 'emergency', 'count' => (int) ($rows->emergency ?? 0)],
        ];
    }

    private function providerPerformance($base): array
    {
        $rows = (clone $base)
            ->whereNotNull('provider')
            ->whereNotNull('response_ms')
            ->selectRaw('provider, COUNT(*) as c, AVG(response_ms) as avg_ms')
            ->groupBy('provider')
            ->orderByDesc('c')
            ->get();

        return $rows->map(fn ($r) => [
            'provider' => $r->provider,
            'count'    => (int) $r->c,
            'avg_ms'   => (int) round((float) $r->avg_ms),
        ])->all();
    }
}
