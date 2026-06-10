<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot-and-stamp engine behind the Seeder Center (مركز السيدر).
 *
 * Instead of editing every insert() in every seeder to add demo columns,
 * we wrap a seeder run: take the max `id` of each stampable table BEFORE it
 * runs, then after it finishes stamp every row whose id is newer than the
 * snapshot with `is_demo = true` + the batch label. Rows created later by
 * the real app keep `demo_batch = null` and are never touched.
 *
 * Tables are read from config/demo_seed.php. The stamper is a no-op for any
 * table that does not yet have the `is_demo` column, so it is safe to call
 * even before the columns migration has run.
 */
class DemoBatch
{
    /**
     * Run $fn (a seeder invocation) and stamp every row it inserts into a
     * stampable table with the given batch label.
     */
    public static function wrap(string $batch, Closure $fn): void
    {
        $before = self::snapshot();
        $fn();
        self::stamp($batch, $before);
    }

    /** @return array<string,int> table => current max id */
    public static function snapshot(): array
    {
        $snapshot = [];

        foreach (self::stampableTables() as $table) {
            $snapshot[$table] = (int) (DB::table($table)->max('id') ?? 0);
        }

        return $snapshot;
    }

    /**
     * Stamp rows newer than the snapshot. Only touches rows that are not
     * already attributed to a batch, so re-running converges instead of
     * overwriting an earlier attribution.
     *
     * @param array<string,int> $before
     */
    public static function stamp(string $batch, array $before): void
    {
        foreach (self::stampableTables() as $table) {
            $floor = $before[$table] ?? 0;

            DB::table($table)
                ->where('id', '>', $floor)
                ->whereNull('demo_batch')
                ->update([
                    'is_demo'    => true,
                    'demo_batch' => $batch,
                ]);
        }
    }

    /**
     * Stampable tables from config that actually exist and already carry the
     * demo columns (so this is inert before the migration has run).
     *
     * @return list<string>
     */
    public static function stampableTables(): array
    {
        return array_values(array_filter(
            config('demo_seed.tables', []),
            fn (string $table) => Schema::hasTable($table)
                && Schema::hasColumn($table, 'id')
                && Schema::hasColumn($table, 'is_demo'),
        ));
    }
}
