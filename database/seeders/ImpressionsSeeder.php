<?php

namespace Database\Seeders;

use App\Enums\ImpressionSource;
use App\Models\Clinic;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Distributes fake impressions across the 6 sources for the last 30
 * days, so the new Stats dashboard has realistic data the moment it
 * loads — without waiting for organic traffic.
 *
 * Distribution shape:
 *   - search:           45%  (primary discovery)
 *   - filter:           20%
 *   - home:             15%
 *   - similar_services: 10%
 *   - ai:                7%  (silent contribution that shows up in
 *                              admin breakdown but not in clinic UI)
 *   - compare:           3%
 *
 * Cascade respected: every service impression also bumps its clinic.
 * The seeder picks ~30% of services per day to receive impressions,
 * so the table sizes stay sane (< 50K rows on a fresh seed).
 */
class ImpressionsSeeder extends Seeder
{
    private const DAYS = 30;

    private const SHARES = [
        ImpressionSource::SEARCH  => 45,
        ImpressionSource::FILTER  => 20,
        ImpressionSource::HOME    => 15,
        ImpressionSource::SIMILAR => 10,
        ImpressionSource::AI      => 7,
        ImpressionSource::COMPARE => 3,
    ];

    public function run(): void
    {
        $clinicIds = Clinic::pluck('id')->all();
        if (empty($clinicIds)) {
            $this->command?->warn('ImpressionsSeeder: no clinics yet — skipped.');
            return;
        }

        // Pre-load service→clinic mapping for the cascade.
        $servicesByClinic = Service::pluck('clinic_id', 'id')->all();
        if (empty($servicesByClinic)) {
            $this->command?->warn('ImpressionsSeeder: no services yet — skipped.');
            return;
        }
        $serviceIds = array_keys($servicesByClinic);

        $clinicRows = [];
        $serviceRows = [];
        $now = now();

        // For each day, pick a random "daily volume" per clinic and
        // distribute it across the 6 sources weighted by SHARES.
        for ($d = self::DAYS - 1; $d >= 0; $d--) {
            $date = CarbonImmutable::now()->subDays($d)->toDateString();

            foreach ($clinicIds as $cid) {
                $dailyClinicVolume = rand(20, 200);
                foreach (self::SHARES as $source => $share) {
                    $count = (int) round(($dailyClinicVolume * $share) / 100);
                    if ($count <= 0) continue;
                    $clinicRows[] = [
                        'clinic_id' => $cid,
                        'date'      => $date,
                        'source'    => $source,
                        'count'     => $count,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            // ~30% of services get a daily impression bucket. Smaller
            // numbers per service so per-source rows stay reasonable.
            shuffle($serviceIds);
            $picked = array_slice($serviceIds, 0, (int) ceil(count($serviceIds) * 0.3));
            foreach ($picked as $sid) {
                $cid = $servicesByClinic[$sid] ?? null;
                if (! $cid) continue;
                $dailyServiceVolume = rand(2, 30);
                foreach (self::SHARES as $source => $share) {
                    if ($source === ImpressionSource::COMPARE) continue; // services don't surface in /compare
                    $count = (int) round(($dailyServiceVolume * $share) / 100);
                    if ($count <= 0) continue;
                    $serviceRows[] = [
                        'service_id' => $sid,
                        'clinic_id'  => $cid,
                        'date'       => $date,
                        'source'     => $source,
                        'count'      => $count,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        // Bulk insert in chunks. Upsert by unique key so re-running the
        // seeder doesn't duplicate.
        foreach (array_chunk($clinicRows, 1000) as $chunk) {
            DB::table('clinic_impressions')->upsert(
                $chunk,
                ['clinic_id', 'date', 'source'],
                ['count' => DB::raw('count + VALUES(count)')],
            );
        }
        foreach (array_chunk($serviceRows, 1000) as $chunk) {
            DB::table('service_impressions')->upsert(
                $chunk,
                ['service_id', 'date', 'source'],
                ['count' => DB::raw('count + VALUES(count)')],
            );
        }

        $this->command?->info('ImpressionsSeeder: ' . count($clinicRows) . ' clinic-source rows + ' . count($serviceRows) . ' service-source rows.');
    }
}
