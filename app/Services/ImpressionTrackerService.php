<?php

namespace App\Services;

use App\Enums\ImpressionSource;
use App\Models\Service;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Multi-source impression bumper. Replaces the legacy
 * ClinicStat::bump(*, 'search_appearances') call sites with a typed
 * tracker that fans out to two tables:
 *
 *   - clinic_impressions: one counter per (clinic, day, source)
 *   - service_impressions: one counter per (service, day, source)
 *
 * Cascade rule (spec §2): a service impression always also bumps the
 * service's clinic. A clinic-only impression never bumps individual
 * services — clinic-level surfacing doesn't visually expose every
 * service the clinic offers.
 *
 * Performance: writes use INSERT … ON DUPLICATE KEY UPDATE so each
 * (entity, day, source) tuple is at most one SQL roundtrip. The
 * `trackMany*` helpers batch within a single transaction so a
 * 12-result search page produces 1 commit, not 24.
 *
 * Failure isolation: write errors are caught + logged. Analytics must
 * never break the user-facing page.
 */
class ImpressionTrackerService
{
    /** Bump exactly one clinic for `$source`. No service-level effect. */
    public function trackClinic(int $clinicId, string $source): void
    {
        $this->trackManyClinics([$clinicId], $source);
    }

    /**
     * Bump a single Service (or service id) for `$source`. Auto-cascades
     * to the service's clinic — passing only the id costs one extra
     * SELECT, so prefer passing a Service model when you already have it.
     */
    public function trackService(Service|int $service, string $source): void
    {
        $this->trackManyServices([$service], $source);
    }

    /** Bulk clinic-only bump. Deduplicates ids. */
    public function trackManyClinics(array $clinicIds, string $source): void
    {
        $ids = array_values(array_unique(array_filter($clinicIds, fn ($v) => $v && is_numeric($v))));
        if (empty($ids) || ! $this->validSource($source)) {
            return;
        }

        try {
            $this->upsertCounters('clinic_impressions', 'clinic_id', $ids, $source);
        } catch (\Throwable $e) {
            Log::warning('ImpressionTrackerService::trackManyClinics failed', [
                'err' => $e->getMessage(), 'source' => $source, 'ids' => $ids,
            ]);
        }
    }

    /**
     * Bulk service bump + automatic cascade to each service's clinic.
     * Accepts mixed Service|int arrays — int items need a single SELECT
     * to resolve their clinic_id.
     */
    public function trackManyServices(array $services, string $source): void
    {
        if (! $this->validSource($source) || empty($services)) {
            return;
        }

        // Split into (service_id, clinic_id) tuples — Service models
        // give us the clinic_id for free; raw ids need a lookup.
        $tuples = collect();
        $missingIds = [];
        foreach ($services as $s) {
            if ($s instanceof Service) {
                if ($s->id && $s->clinic_id) {
                    $tuples->push(['service_id' => $s->id, 'clinic_id' => $s->clinic_id]);
                }
            } elseif (is_numeric($s)) {
                $missingIds[] = (int) $s;
            }
        }

        if (! empty($missingIds)) {
            Service::whereIn('id', array_unique($missingIds))
                ->select(['id', 'clinic_id'])
                ->get()
                ->each(fn ($s) => $tuples->push(['service_id' => $s->id, 'clinic_id' => $s->clinic_id]));
        }

        // De-dupe by service_id within this batch — a single render
        // surface shouldn't double-bump the same service even if our
        // caller passed dupes.
        $tuples = $tuples->unique('service_id')->values();
        if ($tuples->isEmpty()) {
            return;
        }

        try {
            DB::transaction(function () use ($tuples, $source) {
                // Service-level rows.
                $serviceRows = $tuples->map(fn ($t) => [
                    'service_id' => $t['service_id'],
                    'clinic_id'  => $t['clinic_id'],
                    'date'       => today()->toDateString(),
                    'source'     => $source,
                    'count'      => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all();

                DB::table('service_impressions')->upsert(
                    $serviceRows,
                    ['service_id', 'date', 'source'],
                    ['count' => DB::raw('count + 1'), 'updated_at' => now()],
                );

                // Cascade: bump each affected clinic ONCE per service
                // impression. If 3 services from the same clinic surface
                // simultaneously, the clinic gets +3.
                $clinicCounts = $tuples->groupBy('clinic_id')->map->count();
                $clinicRows = $clinicCounts->map(fn ($count, $cid) => [
                    'clinic_id' => (int) $cid,
                    'date'      => today()->toDateString(),
                    'source'    => $source,
                    'count'     => $count,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->values()->all();

                DB::table('clinic_impressions')->upsert(
                    $clinicRows,
                    ['clinic_id', 'date', 'source'],
                    ['count' => DB::raw('count + VALUES(count)'), 'updated_at' => now()],
                );
            });
        } catch (\Throwable $e) {
            Log::warning('ImpressionTrackerService::trackManyServices failed', [
                'err' => $e->getMessage(), 'source' => $source,
                'service_count' => $tuples->count(),
            ]);
        }
    }

    /** Generic upsert+increment for the clinic-level path. */
    private function upsertCounters(string $table, string $idColumn, array $ids, string $source): void
    {
        $now = now();
        $date = today()->toDateString();
        $rows = array_map(fn ($id) => [
            $idColumn    => (int) $id,
            'date'       => $date,
            'source'     => $source,
            'count'      => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], $ids);

        DB::table($table)->upsert(
            $rows,
            [$idColumn, 'date', 'source'],
            ['count' => DB::raw('count + 1'), 'updated_at' => $now],
        );
    }

    private function validSource(string $source): bool
    {
        return in_array($source, ImpressionSource::ALL, true);
    }
}
