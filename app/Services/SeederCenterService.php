<?php

namespace App\Services;

use App\Jobs\ReseedBatchJob;
use App\Models\SeederRun;
use App\Support\DemoBatch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * All logic behind the Seeder Center (مركز السيدر). Every row produced by a
 * demo seeder carries `is_demo = true` + a `demo_batch` label (see
 * App\Support\DemoBatch + config/demo_seed.php). This service lets a super-
 * admin inventory, hide/restore (soft), purge (hard delete), and reseed each
 * batch — without ever touching real, app-entered data (demo_batch = null).
 *
 * It reads/writes via the query builder (DB::) on purpose so the global
 * "hide" scope on the catalogue models never filters out rows we are managing.
 */
class SeederCenterService
{
    /** @return list<string> */
    public function tables(): array
    {
        return array_values(array_filter(
            config('demo_seed.tables', []),
            fn (string $t) => Schema::hasTable($t) && Schema::hasColumn($t, 'is_demo'),
        ));
    }

    /** @return list<string> */
    public function hideableTables(): array
    {
        return array_values(array_filter(
            config('demo_seed.hideable', []),
            fn (string $t) => Schema::hasTable($t) && Schema::hasColumn($t, 'demo_hidden_at'),
        ));
    }

    /**
     * Per-batch summary for the dashboard: how many seeded rows exist, how
     * many are currently hidden, and the batch metadata.
     *
     * @return array<int,array<string,mixed>>
     */
    public function inventory(): array
    {
        $batches  = config('demo_seed.batches', []);
        $tables   = $this->tables();
        $hideable = $this->hideableTables();

        $out = [];

        foreach ($batches as $key => $meta) {
            $total  = 0;
            $hidden = 0;

            foreach ($tables as $table) {
                $total += DB::table($table)->where('demo_batch', $key)->count();
            }
            foreach ($hideable as $table) {
                $hidden += DB::table($table)
                    ->where('demo_batch', $key)
                    ->whereNotNull('demo_hidden_at')
                    ->count();
            }

            $lastRun = SeederRun::where('batch', $key)->latest('id')->first();

            $out[] = [
                'key'        => $key,
                'label_ar'   => $meta['label_ar'] ?? $key,
                'label_en'   => $meta['label_en'] ?? $key,
                'desc_ar'    => $meta['desc_ar'] ?? '',
                'desc_en'    => $meta['desc_en'] ?? '',
                'heavy'      => (bool) ($meta['heavy'] ?? false),
                'total_rows' => $total,
                'hidden_rows' => $hidden,
                'is_hidden'  => $total > 0 && $hidden > 0,
                'hideable'   => $hidden > 0 || $this->batchHasHideableRows($key),
                'last_run'   => $lastRun ? [
                    'status'      => $lastRun->status,
                    'message'     => $lastRun->message,
                    'rows_created' => $lastRun->rows_created,
                    'finished_at' => $lastRun->finished_at?->toIso8601String(),
                ] : null,
            ];
        }

        return $out;
    }

    private function batchHasHideableRows(string $batch): bool
    {
        foreach ($this->hideableTables() as $table) {
            if (DB::table($table)->where('demo_batch', $batch)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Per-table row counts for a single batch (drill-down view).
     *
     * @return array<int,array{table:string,total:int,hidden:int}>
     */
    public function breakdown(string $batch): array
    {
        $this->assertBatch($batch);
        $hideable = $this->hideableTables();
        $rows = [];

        foreach ($this->tables() as $table) {
            $total = DB::table($table)->where('demo_batch', $batch)->count();
            if ($total === 0) {
                continue;
            }
            $hidden = in_array($table, $hideable, true)
                ? DB::table($table)->where('demo_batch', $batch)->whereNotNull('demo_hidden_at')->count()
                : 0;
            $rows[] = ['table' => $table, 'total' => $total, 'hidden' => $hidden];
        }

        return $rows;
    }

    /** Soft-hide a batch's catalogue rows from the whole platform. */
    public function hide(string $batch): int
    {
        $this->assertBatch($batch);
        $now = Carbon::now();
        $affected = 0;

        foreach ($this->hideableTables() as $table) {
            $affected += DB::table($table)
                ->where('demo_batch', $batch)
                ->whereNull('demo_hidden_at')
                ->update(['demo_hidden_at' => $now]);
        }

        return $affected;
    }

    /** Restore (un-hide) a batch — instant, same data, no reseed. */
    public function unhide(string $batch): int
    {
        $this->assertBatch($batch);
        $affected = 0;

        foreach ($this->hideableTables() as $table) {
            $affected += DB::table($table)
                ->where('demo_batch', $batch)
                ->whereNotNull('demo_hidden_at')
                ->update(['demo_hidden_at' => null]);
        }

        return $affected;
    }

    /**
     * Real (is_demo = false) rows that point at demo rows in this batch. If
     * non-empty, purge is blocked so we never cascade-delete or orphan real
     * data (the chosen FK-entanglement policy).
     *
     * @return array<int,array{child:string,parent:string,fk:string,count:int}>
     */
    public function conflicts(string $batch): array
    {
        $this->assertBatch($batch);
        $map = config('demo_seed.conflicts', []);
        $found = [];

        foreach ($map as $child => $relations) {
            if (! Schema::hasTable($child) || ! Schema::hasColumn($child, 'is_demo')) {
                continue;
            }

            foreach ($relations as $fk => $parent) {
                if (! Schema::hasColumn($child, $fk)) {
                    continue;
                }

                $parentIds = DB::table($parent)->where('demo_batch', $batch)->pluck('id');
                if ($parentIds->isEmpty()) {
                    continue;
                }

                $count = DB::table($child)
                    ->whereIn($fk, $parentIds)
                    ->where('is_demo', false)
                    ->count();

                if ($count > 0) {
                    $found[] = [
                        'child'  => $child,
                        'parent' => $parent,
                        'fk'     => $fk,
                        'count'  => $count,
                    ];
                }
            }
        }

        return $found;
    }

    /**
     * Permanently delete a batch. Blocked (RuntimeException) when real rows
     * reference it, unless $force is passed. Pivot rows are cleaned via their
     * parent; FK checks are suspended because we only ever delete demo rows
     * that we have verified have no real children.
     *
     * @return array{deleted:int,conflicts:array}
     */
    public function purge(string $batch, bool $force = false): array
    {
        $this->assertBatch($batch);

        $conflicts = $this->conflicts($batch);
        if ($conflicts !== [] && ! $force) {
            return ['deleted' => 0, 'conflicts' => $conflicts];
        }

        $deleted = 0;

        DB::transaction(function () use ($batch, &$deleted) {
            Schema::disableForeignKeyConstraints();

            // 1) Pivot rows whose parent belongs to the batch.
            foreach (config('demo_seed.pivots', []) as $pivot => $link) {
                if (! Schema::hasTable($pivot)) {
                    continue;
                }
                foreach ($link as $parentKey => $parentTable) {
                    $parentIds = DB::table($parentTable)->where('demo_batch', $batch)->pluck('id');
                    if ($parentIds->isNotEmpty()) {
                        $deleted += DB::table($pivot)->whereIn($parentKey, $parentIds)->delete();
                    }
                }
            }

            // 2) Every stamped row of the batch (hard delete, bypasses soft-delete).
            foreach ($this->tables() as $table) {
                $deleted += DB::table($table)->where('demo_batch', $batch)->delete();
            }

            Schema::enableForeignKeyConstraints();
        });

        return ['deleted' => $deleted, 'conflicts' => []];
    }

    /**
     * Re-run a batch's seeder to regenerate purged data. Light batches run
     * synchronously (instant feedback); heavy ones go to the queue and report
     * progress through the SeederRun row the caller polls.
     */
    public function reseed(string $batch, ?int $adminId = null, ?string $adminName = null): SeederRun
    {
        $meta = $this->assertBatch($batch);

        $run = SeederRun::create([
            'batch'           => $batch,
            'status'          => SeederRun::STATUS_QUEUED,
            'created_by'      => $adminId,
            'created_by_name' => $adminName,
        ]);

        if (! empty($meta['heavy'])) {
            ReseedBatchJob::dispatch($run->id);
        } else {
            ReseedBatchJob::dispatchSync($run->id);
            $run->refresh();
        }

        return $run;
    }

    /**
     * Executed by ReseedBatchJob (or synchronously). Runs the batch's seeder
     * wrapped in DemoBatch so the regenerated rows are re-stamped.
     */
    public function runSeederForBatch(SeederRun $run): void
    {
        $meta  = $this->assertBatch($run->batch);
        $class = $meta['seeder'];

        $run->update(['status' => SeederRun::STATUS_RUNNING, 'started_at' => Carbon::now()]);

        try {
            $created = 0;
            DemoBatch::wrap($run->batch, function () use ($class, &$created) {
                $before = DemoBatch::snapshot();
                app(\Illuminate\Contracts\Console\Kernel::class)
                    ->call('db:seed', ['--class' => $class, '--force' => true]);
                $after = DemoBatch::snapshot();
                foreach ($after as $table => $max) {
                    $created += max(0, $max - ($before[$table] ?? 0));
                }
            });

            $run->update([
                'status'       => SeederRun::STATUS_DONE,
                'rows_created' => $created,
                'finished_at'  => Carbon::now(),
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status'      => SeederRun::STATUS_FAILED,
                'message'     => mb_substr($e->getMessage(), 0, 1000),
                'finished_at' => Carbon::now(),
            ]);
            throw $e;
        }
    }

    /** @return array<string,mixed> the batch meta */
    private function assertBatch(string $batch): array
    {
        $batches = config('demo_seed.batches', []);
        if (! isset($batches[$batch])) {
            throw new RuntimeException("Unknown demo batch: {$batch}");
        }

        return $batches[$batch];
    }
}
