<?php

namespace App\Jobs;

use App\Models\SeederRun;
use App\Services\SeederCenterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Re-runs one demo batch's seeder to regenerate data the super-admin purged
 * from the Seeder Center. Heavy batches (massive_city, year_of_usage, …) are
 * dispatched here so the request returns immediately; the SeederRun row is
 * updated with progress/status for the React page to poll.
 *
 * Light batches are dispatchSync'd by SeederCenterService::reseed so the
 * admin gets instant feedback in the same request.
 */
class ReseedBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    // Heavy seeders can run for several minutes.
    public int $timeout = 1800;

    public function __construct(public readonly int $seederRunId)
    {
    }

    public function handle(SeederCenterService $service): void
    {
        $run = SeederRun::find($this->seederRunId);
        if (! $run) {
            return;
        }

        $service->runSeederForBatch($run);
    }
}
