<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiAssistantLog;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Compact AI summary card for the admin dashboard.
 *
 * Cached for `ai_widget_cache_ttl` seconds (default 60). All alert
 * thresholds (`ai_alert_emergency_window_hours`, `ai_block_spike_*`)
 * are admin-tunable too.
 */
class AiCenterDashboardWidgetController extends Controller
{
    public function show(): JsonResponse
    {
        $ttl = max(1, (int) SystemSetting::get('ai_widget_cache_ttl', 60));

        $data = Cache::remember('ai_center:dashboard_widget:v1', $ttl, function () {
            $today  = today();
            $startOfToday = $today->copy()->startOfDay();
            $startOfYesterday = $today->copy()->subDay()->startOfDay();
            $endOfYesterday   = $today->copy()->startOfDay();

            $todayCount = AiAssistantLog::where('created_at', '>=', $startOfToday)->count();
            $yesterdayCount = AiAssistantLog::whereBetween('created_at', [$startOfYesterday, $endOfYesterday])->count();

            // Top topic today: first 4 words of the user query, most
            // frequent. Skip blocked + emergency rows so the headline
            // reflects organic intent.
            $topTopic = AiAssistantLog::where('created_at', '>=', $startOfToday)
                ->where('was_blocked', false)
                ->where('was_emergency', false)
                ->selectRaw('SUBSTRING_INDEX(query, " ", 4) as topic, COUNT(*) as c')
                ->groupBy('topic')
                ->orderByDesc('c')
                ->limit(1)
                ->first();

            return [
                'today_count'      => $todayCount,
                'yesterday_count'  => $yesterdayCount,
                'top_topic'        => $topTopic ? [
                    'text'  => (string) $topTopic->topic,
                    'count' => (int) $topTopic->c,
                ] : null,
                'alert'            => $this->buildAlert(),
            ];
        });

        return response()->json(['data' => $data]);
    }

    private function buildAlert(): ?array
    {
        $emergencyWindowHours = max(1, (int) SystemSetting::get('ai_alert_emergency_window_hours', 24));
        $spikeThreshold       = max(1.0, (float) SystemSetting::get('ai_block_spike_threshold', 3.0));
        $spikeMinVolume       = max(1, (int) SystemSetting::get('ai_block_spike_min_volume', 20));

        // 1) Most recent emergency within the admin-tunable window.
        $emergency = AiAssistantLog::where('was_emergency', true)
            ->where('created_at', '>=', now()->subHours($emergencyWindowHours))
            ->latest('id')
            ->first(['id', 'created_at']);
        if ($emergency) {
            return [
                'kind'       => 'emergency',
                'created_at' => $emergency->created_at?->toIso8601String(),
            ];
        }

        // 2) Block-rate spike — today's blocked-rate vs 30-day baseline.
        $todayCount   = AiAssistantLog::where('created_at', '>=', today())->count();
        $todayBlocked = AiAssistantLog::where('created_at', '>=', today())->where('was_blocked', true)->count();
        if ($todayCount >= $spikeMinVolume) {
            $todayRate = $todayBlocked / max(1, $todayCount);
            $base = AiAssistantLog::where('created_at', '>=', now()->subDays(30))->count();
            $baseBlocked = AiAssistantLog::where('created_at', '>=', now()->subDays(30))->where('was_blocked', true)->count();
            $baseRate = $base > 0 ? $baseBlocked / $base : 0;
            if ($baseRate > 0 && $todayRate >= $baseRate * $spikeThreshold) {
                return [
                    'kind'  => 'block_rate_spike',
                    'ratio' => round($todayRate / $baseRate, 2),
                ];
            }
        }

        return null;
    }
}
