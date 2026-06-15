<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brings landing_pages + landing_page_blocks into the Seeder Center (مركز
 * السيدر). Mirrors 2026_06_10_140000 — the foundational columns migration —
 * which only stamped the tables that existed when it ran (landing pages came
 * later). With these columns + the config registration, the demo
 * LandingPageSeeder's rows can be hidden / restored / purged like any other
 * demo batch, and real (app-created) landing pages stay untouched.
 */
return new class extends Migration
{
    private array $tables = ['landing_pages', 'landing_page_blocks'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
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
        foreach ($this->tables as $table) {
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
