<?php

namespace App\Console\Commands;

use App\Models\Clinic;
use App\Services\GooglePlacesService;
use Illuminate\Console\Command;

class SyncGoogleReviews extends Command
{
    protected $signature = 'saerha:sync-google-reviews';
    protected $description = 'Sync Google reviews for all active clinics that have a Google Place ID.';

    public function handle(GooglePlacesService $places): int
    {
        $clinics = Clinic::where('status', 'active')
            ->whereNotNull('google_place_id')
            ->get();

        $totalFetched = 0;
        $failed = 0;

        foreach ($clinics as $clinic) {
            $result = $places->syncReviews($clinic);
            if ($result['success']) {
                $totalFetched += $result['fetched'];
            } else {
                $failed++;
                $this->warn("Clinic {$clinic->id}: {$result['message']}");
            }
        }

        $this->info("Synced {$totalFetched} reviews across {$clinics->count()} clinics ({$failed} failed).");

        return self::SUCCESS;
    }
}
