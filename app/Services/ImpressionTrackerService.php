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
    /**
     * 3-hour window for unique-viewer ("المشاهدات") de-duplication: the same
     * visitor seeing the same clinic again inside this window is not counted
     * as a new view. 10800 seconds = 3 hours. See clinic_views migration.
     */
    private const VIEW_WINDOW_SECONDS = 10800;

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

        // Unique-viewer tracking runs alongside the raw impression bump.
        $this->recordViews($ids);
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

        // A service impression visually surfaces its clinic, so the same
        // visitor also counts as a unique viewer of each affected clinic.
        $this->recordViews($tuples->pluck('clinic_id')->all());
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

    /**
     * Record the current visitor as a unique viewer of each clinic, scoped
     * to the active 3-hour window. insertOrIgnore against the unique key
     * (clinic_id, bucket, visitor_hash) makes repeated views by the same
     * visitor inside the window no-ops, so "المشاهدات" counts distinct
     * visitors while "الظهور" counts every appearance.
     *
     * Failure-isolated and no-ops outside an HTTP/session context (e.g. a
     * queued AI reply) where no visitor identity is available.
     */
    private function recordViews(array $clinicIds): void
    {
        $ids = array_values(array_unique(array_filter($clinicIds, fn ($v) => $v && is_numeric($v))));
        if (empty($ids)) {
            return;
        }

        $hash = $this->visitorHash();
        if ($hash === null) {
            return;
        }

        $bucket = intdiv(now()->timestamp, self::VIEW_WINDOW_SECONDS);
        $date = today()->toDateString();
        $now = now();

        $rows = array_map(fn ($id) => [
            'clinic_id'    => (int) $id,
            'date'         => $date,
            'bucket'       => $bucket,
            'visitor_hash' => $hash,
            'created_at'   => $now,
            'updated_at'   => $now,
        ], $ids);

        try {
            DB::table('clinic_views')->insertOrIgnore($rows);
        } catch (\Throwable $e) {
            Log::warning('ImpressionTrackerService::recordViews failed', [
                'err' => $e->getMessage(), 'ids' => $ids,
            ]);
        }
    }

    /**
     * Stable, privacy-preserving identifier for the current visitor:
     * the logged-in user id when authenticated, otherwise the session id.
     * Hashed so no raw id/session token is persisted. Returns null when no
     * session is bound to the request (no visitor to attribute).
     */
    private function visitorHash(): ?string
    {
        try {
            if (auth()->check()) {
                return hash('sha256', 'u:' . auth()->id());
            }

            $sessionId = session()->getId();

            return $sessionId ? hash('sha256', 's:' . $sessionId) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
