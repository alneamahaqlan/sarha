<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Promote services.category_id (1:1) to a many-to-many relation so a single
 * service can belong to multiple specialties (e.g. "ليزر إزالة شعر" is both
 * dermatology + cosmetics).
 *
 * Also extends category_requests with a nullable service_id so a request
 * submitted from inside a service form can auto-attach the new specialty
 * to that service when the admin approves it.
 *
 * Order of operations:
 *   1) create category_service pivot
 *   2) backfill: every (service_id, category_id) becomes a pivot row
 *   3) drop services.category_id (FK + index + column)
 *   4) add nullable category_requests.service_id FK
 *
 * Down: restores services.category_id as nullable and copies the FIRST
 * pivot row back, then drops the pivot + request column. Cannot reconstruct
 * the multiple-category choice — best-effort only.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Pivot table.
        Schema::create('category_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['service_id', 'category_id']);
            $table->index(['category_id', 'service_id']);
        });

        // 2. Backfill — one pivot row per existing service.
        if (Schema::hasColumn('services', 'category_id')) {
            DB::statement("
                INSERT INTO category_service (service_id, category_id, created_at, updated_at)
                SELECT id, category_id, NOW(), NOW()
                FROM services
                WHERE category_id IS NOT NULL
            ");
        }

        // 3. Drop services.category_id (FK + composite index + column).
        if (Schema::hasColumn('services', 'category_id')) {
            Schema::table('services', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
                try {
                    $table->dropIndex(['category_id', 'is_active']);
                } catch (\Throwable) {
                    // Index naming may vary across MySQL versions; ignore if gone.
                }
                $table->dropColumn('category_id');
            });
        }

        // 4. Link category_requests to the originating service so approval
        //    can auto-attach the new category back to that service.
        if (! Schema::hasColumn('category_requests', 'service_id')) {
            Schema::table('category_requests', function (Blueprint $table) {
                $table->foreignId('service_id')
                    ->nullable()
                    ->after('clinic_id')
                    ->constrained()
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Restore services.category_id (nullable — we can't enforce required
        // without losing rows that had multiple categories).
        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('sub_clinic_id')
                ->constrained('categories')
                ->restrictOnDelete();
            $table->index(['category_id', 'is_active']);
        });

        // Copy the FIRST pivot row back per service.
        DB::statement("
            UPDATE services s
            SET category_id = (
                SELECT category_id FROM category_service
                WHERE service_id = s.id
                ORDER BY id LIMIT 1
            )
        ");

        Schema::dropIfExists('category_service');

        if (Schema::hasColumn('category_requests', 'service_id')) {
            Schema::table('category_requests', function (Blueprint $table) {
                $table->dropForeign(['service_id']);
                $table->dropColumn('service_id');
            });
        }
    }
};
