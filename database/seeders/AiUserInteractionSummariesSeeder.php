<?php

namespace Database\Seeders;

use App\Models\AiAssistantLog;
use App\Models\AiUserInteractionSummary;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Phase 2 backfill: gives the existing Phase-1 fake logs
 *   (a) a conversation_id grouping (sliding 30-minute window per actor)
 *   (b) one summary row per authenticated-user log that's of a kind
 *       worth analyzing (matched / details / follow_up / freeform)
 *
 * Idempotent — running multiple times is safe.
 */
class AiUserInteractionSummariesSeeder extends Seeder
{
    public function run(): void
    {
        $this->backfillConversationIds();
        $this->backfillSummaries();
    }

    /**
     * Group logs into conversations: same user/visitor + within 30
     * minutes of the previous turn. Walks rows in chronological order.
     */
    private function backfillConversationIds(): void
    {
        // Process logs that don't have a conversation_id yet, ordered by
        // actor + time so the gap rule works.
        $missing = AiAssistantLog::query()
            ->whereNull('conversation_id')
            ->orderByRaw('COALESCE(user_id, 0), visitor_id, created_at')
            ->select(['id', 'user_id', 'visitor_id', 'created_at'])
            ->get();

        if ($missing->isEmpty()) {
            $this->command?->info('AiUserInteractionSummariesSeeder: nothing to backfill (conversation_id).');
            return;
        }

        $assignments = [];
        $lastActorKey = null;
        $lastAt = null;
        $currentConvId = null;

        foreach ($missing as $log) {
            $actorKey = ($log->user_id ?: 'v:' . $log->visitor_id);
            $newConversation = $actorKey !== $lastActorKey
                || $lastAt === null
                || $log->created_at->diffInMinutes($lastAt) > 30;

            if ($newConversation || $currentConvId === null) {
                $currentConvId = (string) Str::uuid();
            }

            $assignments[] = ['id' => $log->id, 'conversation_id' => $currentConvId];
            $lastActorKey = $actorKey;
            $lastAt = $log->created_at;
        }

        // Bulk UPDATE via CASE — fewer roundtrips than 400× single updates.
        foreach (array_chunk($assignments, 500) as $chunk) {
            $cases = [];
            $ids = [];
            $bindings = [];
            foreach ($chunk as $row) {
                $cases[] = "WHEN ? THEN ?";
                $bindings[] = $row['id'];
                $bindings[] = $row['conversation_id'];
                $ids[] = $row['id'];
            }
            $idsList = implode(',', $ids);
            $caseSql = implode(' ', $cases);
            DB::update("UPDATE ai_assistant_logs SET conversation_id = CASE id {$caseSql} END WHERE id IN ({$idsList})", $bindings);
        }

        $this->command?->info('AiUserInteractionSummariesSeeder: backfilled conversation_id on ' . count($assignments) . ' logs.');
    }

    /**
     * Generate one summary row per authenticated-user log of a relevant
     * kind. Heuristic topic + seriousness — same logic the real
     * SummarizeUserInteractionJob uses as a fallback.
     */
    private function backfillSummaries(): void
    {
        // Already covered?
        $existing = AiUserInteractionSummary::pluck('log_id')->all();
        $existingSet = array_flip($existing);

        $logs = AiAssistantLog::query()
            ->whereNotNull('user_id')
            ->whereIn('kind', ['matched', 'details', 'follow_up', 'freeform'])
            ->whereNotIn('id', $existing ?: [0])
            ->with(['clinics:id', 'categories:id'])
            ->get();

        if ($logs->isEmpty()) {
            $this->command?->info('AiUserInteractionSummariesSeeder: no new summaries to backfill.');
            return;
        }

        $rows = [];
        foreach ($logs as $log) {
            [$topic, $seriousness] = $this->heuristic($log);
            $rows[] = [
                'user_id'      => $log->user_id,
                'log_id'       => $log->id,
                'topic'        => $topic,
                'categories'   => json_encode($log->categories->pluck('id')->all()),
                'clinics'      => json_encode($log->clinics->pluck('id')->all()),
                'seriousness'  => $seriousness,
                'model_used'   => $log->model,
                'generated_at' => $log->created_at,
                'created_at'   => now(),
                'updated_at'   => now(),
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('ai_user_interaction_summaries')->insert($chunk);
        }

        $this->command?->info('AiUserInteractionSummariesSeeder: wrote ' . count($rows) . ' summaries.');
    }

    private function heuristic(AiAssistantLog $log): array
    {
        $topic = Str::limit((string) $log->query, 140);
        $q = mb_strtolower($log->query ?? '');

        $nearDecision = ['احجز', 'أبي احجز', 'أريد حجز', 'أبدأ العلاج', 'تواصل معي', 'احجز موعد'];
        $comparing    = ['أيهما', 'مقارنة', 'الفرق بين', 'أرخص', 'أفضل', 'كم سعر', 'بكم'];

        $seriousness = 'exploration';
        foreach ($nearDecision as $needle) {
            if (mb_strpos($q, $needle) !== false) { $seriousness = 'near_decision'; break; }
        }
        if ($seriousness === 'exploration') {
            foreach ($comparing as $needle) {
                if (mb_strpos($q, $needle) !== false) { $seriousness = 'comparing'; break; }
            }
        }

        // Bias half of comparing/exploration to near_decision so the
        // seeded distribution covers all three buckets visibly in the UI.
        if ($seriousness !== 'near_decision' && rand(0, 99) < 20) {
            $seriousness = 'near_decision';
        }

        return [$topic, $seriousness];
    }
}
