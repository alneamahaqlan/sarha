<?php

namespace App\Jobs;

use App\Models\AiAssistantLog;
use App\Models\AiUserInteractionSummary;
use App\Models\SystemSetting;
use App\Services\AiAssistantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queue job — runs after a chat turn from an authenticated user, builds
 * an admin-facing summary of the thread (topic + categories + clinics +
 * seriousness bucket) and stores it for the user-profile section.
 *
 * Behavior:
 *  - If the AI freeform feature is disabled or no provider key is set,
 *    fall back to a heuristic summary (uses the raw query text). This
 *    keeps the user-profile page populated without surprise LLM bills.
 *  - On success, upserts one row per (user_id, conversation_id) — the
 *    summary always represents the LATEST state of the thread.
 *
 * Failures are absorbed (Log::warning) — a missed summary is recoverable
 * by the next chat turn, and we don't want a stuck queue worker.
 */
class SummarizeUserInteractionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $logId)
    {
    }

    public function handle(AiAssistantService $ai): void
    {
        $log = AiAssistantLog::with(['clinics:id', 'categories:id'])->find($this->logId);
        if (! $log || ! $log->user_id) {
            return;
        }

        try {
            // Heuristic path — also used as the LLM fallback. Always
            // populated so the user-profile page has something.
            [$topic, $seriousness] = $this->heuristicSummary($log);

            AiUserInteractionSummary::updateOrCreate(
                ['user_id' => $log->user_id, 'log_id' => $log->id],
                [
                    'topic'        => $topic,
                    'categories'   => $log->categories->pluck('id')->all(),
                    'clinics'      => $log->clinics->pluck('id')->all(),
                    'seriousness'  => $seriousness,
                    'model_used'   => $log->model,
                    'generated_at' => now(),
                ]
            );

            // Live LLM call is intentionally gated by a dedicated setting
            // (`ai_summarize_use_llm`, default false) so the platform
            // owner opts in explicitly. When enabled, an additional
            // service method on AiAssistantService can replace `topic`
            // with a richer LLM-produced description — costs API budget
            // per logged-in chat turn, so it's off by default.
            if (SystemSetting::get('ai_summarize_use_llm')) {
                // Reserved hook — implement when a billing budget is
                // explicitly allocated for per-user summarization.
            }
        } catch (\Throwable $e) {
            Log::warning('SummarizeUserInteractionJob failed', [
                'log_id' => $this->logId,
                'err'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Cheap rule-based summary used when no LLM is available. Topic is
     * the user's query (truncated); seriousness is inferred from
     * surface signals — comparison words bump it up to "comparing",
     * decisive verbs ("احجز", "أبي") to "near_decision".
     */
    private function heuristicSummary(AiAssistantLog $log): array
    {
        $topic = \Illuminate\Support\Str::limit((string) $log->query, 140);
        $q = mb_strtolower($log->query ?? '');

        // Admin-editable keyword lists. Empty/missing values fall back
        // to the code defaults so the system never goes mute.
        $nearDecision = (array) (SystemSetting::get('ai_seriousness_keywords_near_decision')
            ?: ['احجز', 'أبي احجز', 'أريد حجز', 'أبدأ العلاج', 'تواصل معي', 'احجز موعد']);
        $comparing    = (array) (SystemSetting::get('ai_seriousness_keywords_comparing')
            ?: ['أيهما', 'مقارنة', 'الفرق بين', 'أرخص', 'أفضل', 'كم سعر', 'بكم']);

        $seriousness = 'exploration';
        foreach ($nearDecision as $needle) {
            if ($needle && mb_strpos($q, mb_strtolower((string) $needle)) !== false) { $seriousness = 'near_decision'; break; }
        }
        if ($seriousness === 'exploration') {
            foreach ($comparing as $needle) {
                if ($needle && mb_strpos($q, mb_strtolower((string) $needle)) !== false) { $seriousness = 'comparing'; break; }
            }
        }

        return [$topic, $seriousness];
    }
}
