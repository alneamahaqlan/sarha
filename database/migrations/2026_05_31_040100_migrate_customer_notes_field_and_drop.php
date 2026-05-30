<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migrate the legacy single-field Customer.notes column (phase 2)
 * into the new customer_notes thread (one row per non-null note),
 * then drop the legacy column so there's a single source of truth.
 *
 * created_by_* fields stay null because we never knew who wrote the
 * single-field note. created_by_name = '—' so the UI doesn't crash.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('customers', 'notes')) {
            DB::statement(<<<SQL
                INSERT INTO customer_notes
                    (customer_id, body, is_pinned, created_by_name, created_at, updated_at)
                SELECT
                    id, notes, 1, '—', NOW(), NOW()
                FROM customers
                WHERE notes IS NOT NULL AND notes <> ''
            SQL);

            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('notes');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('customers', 'notes')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->text('notes')->nullable();
            });
        }
    }
};
