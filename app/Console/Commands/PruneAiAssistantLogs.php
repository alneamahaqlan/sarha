<?php

namespace App\Console\Commands;

use App\Models\AiAssistantLog;
use App\Models\SystemSetting;
use Illuminate\Console\Command;

/**
 * Deletes ai_assistant_logs (and their cascading pivots + interaction
 * summaries via FK) older than `ai_logs_retention_days` setting.
 *
 * Setting value of 0 (default) = disabled (no pruning happens). Any
 * positive integer N = delete rows where created_at < now() - N days.
 *
 * Usage: `php artisan ai:prune-logs` — typically scheduled daily.
 */
class PruneAiAssistantLogs extends Command
{
    protected $signature = 'ai:prune-logs {--dry-run : Report what would be deleted without actually deleting}';
    protected $description = 'Delete AI assistant logs older than the configured retention window.';

    public function handle(): int
    {
        $days = (int) SystemSetting::get('ai_logs_retention_days', 0);

        if ($days <= 0) {
            $this->info('ai_logs_retention_days is 0 — retention disabled, nothing to prune.');
            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);
        $query  = AiAssistantLog::where('created_at', '<', $cutoff);
        $count  = $query->count();

        if ($count === 0) {
            $this->info("No logs older than {$days} days (cutoff: {$cutoff->toDateString()}).");
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("DRY RUN — would delete {$count} logs older than {$days} days.");
            return self::SUCCESS;
        }

        // FK cascades drop pivot + summary rows automatically. Chunked
        // delete to keep memory + replication lag reasonable on large
        // tables.
        $deleted = 0;
        $query->orderBy('id')->chunkById(1000, function ($chunk) use (&$deleted) {
            $ids = $chunk->pluck('id')->all();
            AiAssistantLog::whereIn('id', $ids)->delete();
            $deleted += count($ids);
        });

        $this->info("Pruned {$deleted} AI assistant logs older than {$days} days.");
        return self::SUCCESS;
    }
}
