<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Seeder Center (مركز السيدر) foundation.
     *
     * Adds three columns to every table that receives seeded rows
     * (config/demo_seed.php → tables):
     *   - is_demo        : true for seeded rows, false for real app data
     *   - demo_batch     : which seeder produced the row (e.g. 'year_of_usage')
     *   - demo_hidden_at : soft-hide timestamp (instant hide/restore) — only
     *                      meaningful on the 'hideable' catalogue tables
     *
     * The migration is idempotent (hasColumn guards) so it can co-exist with
     * a database that already has some of these columns.
     */
    public function up(): void
    {
        foreach (config('demo_seed.tables', []) as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'is_demo')) {
                    $t->boolean('is_demo')->default(false)->index();
                }
                if (! Schema::hasColumn($table, 'demo_batch')) {
                    $t->string('demo_batch', 40)->nullable()->index();
                }
                if (! Schema::hasColumn($table, 'demo_hidden_at')) {
                    $t->timestamp('demo_hidden_at')->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (config('demo_seed.tables', []) as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                foreach (['is_demo', 'demo_batch', 'demo_hidden_at'] as $col) {
                    if (Schema::hasColumn($table, $col)) {
                        $t->dropColumn($col);
                    }
                }
            });
        }
    }
};
