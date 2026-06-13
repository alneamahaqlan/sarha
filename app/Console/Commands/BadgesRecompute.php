<?php

namespace App\Console\Commands;

use App\Services\ClinicBadgeService;
use Illuminate\Console\Command;

/**
 * Re-evaluates every active automatic badge (Badges Center) and re-stamps its
 * winning complexes. Manual badge assignments are left untouched.
 *
 * Scheduled daily in bootstrap/app.php. Idempotent — safe to run on demand
 * (the admin "recompute now" button calls the same service).
 */
class BadgesRecompute extends Command
{
    protected $signature = 'saerha:badges-recompute';
    protected $description = 'Recompute automatic badges (most booked / fastest growing / top rated …) and re-stamp the winning complexes.';

    public function handle(ClinicBadgeService $badges): int
    {
        $summary = $badges->recompute();

        if (empty($summary)) {
            $this->info('No active automatic badges to recompute.');
            return self::SUCCESS;
        }

        foreach ($summary as $key => $count) {
            $this->info("Badge [{$key}]: {$count} complex(es).");
        }

        return self::SUCCESS;
    }
}
