<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidates the (redundant) service_categories lookup into the existing
 * categories table. After this migration:
 *   - services.category_id  → references categories.id (required, 1:N)
 *   - services.service_category_id  → dropped
 *   - service_categories  → dropped
 *
 * Categories already represented every specialty needed (dentistry,
 * dermatology, ophthalmology, …), so each service_category here was
 * really a sub-grouping of one of them. The slug map below collapses
 * those sub-groupings to their parent specialty without losing data:
 * the granular procedure information stays available in services.name
 * (which the search already tokenizes), so a customer searching for
 * "ليزر إزالة شعر" still matches.
 *
 * Order of operations:
 *   1) add nullable services.category_id
 *   2) backfill from services.service_category_id via the slug map
 *   3) drop services.service_category_id FK + column + index
 *   4) drop service_categories table
 *
 * Down: best-effort restore (re-creates the lookup + the old column,
 * sets every service.service_category_id to NULL, leaves new
 * category_id in place). Production rollback should be paired with a
 * data snapshot — we cannot reconstruct the original sub-group
 * choice from the parent category alone.
 */
return new class extends Migration
{
    /**
     * Old service_categories.slug → new categories.slug. Anything not
     * listed falls back to "family-medicine" so no row is ever orphaned.
     */
    private const SLUG_MAP = [
        // Dentistry sub-categories → dentistry
        'cleaning-polishing'         => 'dentistry',
        'teeth-whitening'            => 'dentistry',
        'dental-fillings'            => 'dentistry',
        'dental-implants'            => 'dentistry',
        'orthodontics'               => 'dentistry',
        'root-canal'                 => 'dentistry',
        'extraction-oral-surgery'    => 'dentistry',
        'hollywood-smile-veneers'    => 'dentistry',
        'dental-prosthetics'         => 'dentistry',
        // Dermatology + laser → dermatology
        'dermatology-consults'       => 'dermatology',
        'laser-hair-removal'         => 'dermatology',
        'skin-resurfacing-laser'     => 'dermatology',
        'peeling-brightening'        => 'dermatology',
        'facials-skincare'           => 'dermatology',
        // Cosmetic injection groups → cosmetics
        'botox-filler-injections'    => 'cosmetics',
        'mesotherapy'                => 'cosmetics',
        'body-contouring'            => 'cosmetics',
        // Eye care → ophthalmology
        'eye-exams'                  => 'ophthalmology',
        'vision-correction-lasik'    => 'ophthalmology',
        'eye-surgeries-diseases'     => 'ophthalmology',
        // Direct 1:1
        'pediatrics-vaccinations'    => 'pediatrics',
        'gynecology-obstetrics'      => 'gynecology',
        'orthopedics-joints'         => 'orthopedics',
        'physical-therapy-rehab'     => 'physical-therapy',
        'cardiology'                 => 'cardiology',
        'internal-medicine'          => 'internal-medicine',
        'ent'                        => 'ent',
        'mental-health'              => 'psychiatry',
        'nutrition-weight-loss'      => 'nutrition',
        'lab-tests'                  => 'labs',
        'radiology-imaging'          => 'radiology',
        'urology'                    => 'urology',
        'general-surgery'            => 'general-surgery',
        // Catch-all bucket
        'general-consultations'      => 'family-medicine',
    ];

    public function up(): void
    {
        // 1. Add the new FK pointing at the unified categories table.
        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('sub_clinic_id')
                ->constrained('categories')
                ->restrictOnDelete();

            $table->index(['category_id', 'is_active']);
        });

        // 2. Backfill — only if the previous tables exist (no-op on fresh DBs).
        if (Schema::hasColumn('services', 'service_category_id')
            && Schema::hasTable('service_categories')) {

            // Resolve "old service_category slug → new category id".
            $oldRows = DB::table('service_categories')->pluck('slug', 'id'); // id => old slug
            $catIds  = DB::table('categories')->pluck('id', 'slug');         // new slug => id
            $fallbackId = $catIds['family-medicine'] ?? $catIds->first();

            $idMap = []; // old service_category_id => new category id
            foreach ($oldRows as $oldId => $oldSlug) {
                $targetSlug = self::SLUG_MAP[$oldSlug] ?? 'family-medicine';
                $idMap[$oldId] = $catIds[$targetSlug] ?? $fallbackId;
            }

            // Apply per-id updates — small loop, only as many statements as
            // service_categories rows (35), so we don't need a giant CASE.
            foreach ($idMap as $oldId => $newId) {
                DB::table('services')
                    ->where('service_category_id', $oldId)
                    ->update(['category_id' => $newId]);
            }

            // Catch any rows whose service_category_id was NULL or pointed at
            // a missing service_category — they go to the fallback.
            DB::table('services')->whereNull('category_id')->update(['category_id' => $fallbackId]);
        }

        // 3. Drop the old FK + column + composite index.
        if (Schema::hasColumn('services', 'service_category_id')) {
            Schema::table('services', function (Blueprint $table) {
                $table->dropForeign(['service_category_id']);
                try {
                    $table->dropIndex(['service_category_id', 'is_active']);
                } catch (\Throwable) {
                    // Index naming can vary; ignore if it's already gone.
                }
                $table->dropColumn('service_category_id');
            });
        }

        // 4. Finally drop the redundant lookup table.
        Schema::dropIfExists('service_categories');
    }

    public function down(): void
    {
        // Best-effort: re-create the lookup table + column shapes. Per-row
        // sub-category choice cannot be reconstructed from category_id alone.
        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->string('emoji')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('service_category_id')
                ->nullable()
                ->after('sub_clinic_id')
                ->constrained('service_categories')
                ->restrictOnDelete();
            $table->index(['service_category_id', 'is_active']);

            $table->dropForeign(['category_id']);
            try {
                $table->dropIndex(['category_id', 'is_active']);
            } catch (\Throwable) {
            }
            $table->dropColumn('category_id');
        });
    }
};
