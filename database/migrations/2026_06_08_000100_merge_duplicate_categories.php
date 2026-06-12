<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Merges duplicate category rows that share an Arabic display name.
 *
 * DemoSeeder created 'Obstetrics' (slug obstetrics) and 'Physiotherapy'
 * (slug physiotherapy) with the SAME Arabic names as the canonical
 * 'Gynecology' (نساء وولادة) and 'Physical Therapy' (علاج طبيعي) rows
 * from DatabaseSeeder. A complex tagged with both renders the specialty
 * twice. These pairs are the same specialty — so we fold the redundant
 * row into the canonical one everywhere it's referenced, then delete it.
 *
 * Keyed by SLUG (not hard-coded ids) so it's safe across environments
 * and a no-op once clean. Irreversible (rows are deleted) → down() is a
 * documented no-op.
 */
return new class extends Migration
{
    /** redundant slug => canonical slug it folds into. */
    private const MERGES = [
        'obstetrics'    => 'gynecology',
        'physiotherapy' => 'physical-therapy',
    ];

    /**
     * Tables referencing categories.id. Pivots carry a unique constraint
     * on (owner, category_id) so re-pointing can collide — those need a
     * de-dupe delete first. Plain FK columns just get re-pointed.
     */
    private const PIVOTS = [
        ['table' => 'clinic_categories',          'owner' => 'clinic_id'],
        ['table' => 'category_service',           'owner' => 'service_id'],
        ['table' => 'ai_assistant_log_categories','owner' => 'log_id'],
    ];

    private const FK_COLUMNS = [
        'sub_clinics',
        'catalog_services',
        'category_requests',
    ];

    public function up(): void
    {
        DB::transaction(function () {
            foreach (self::MERGES as $sourceSlug => $targetSlug) {
                $source = DB::table('categories')->where('slug', $sourceSlug)->first();
                $target = DB::table('categories')->where('slug', $targetSlug)->first();

                // Already clean (source removed) or canonical missing → skip.
                if (! $source || ! $target) {
                    continue;
                }

                foreach (self::PIVOTS as $p) {
                    // Drop source links whose owner already points at the
                    // target (would violate the unique constraint on update).
                    DB::statement(
                        "DELETE s FROM {$p['table']} s
                         JOIN {$p['table']} t
                           ON t.{$p['owner']} = s.{$p['owner']}
                          AND t.category_id = ?
                         WHERE s.category_id = ?",
                        [$target->id, $source->id],
                    );
                    // Re-point the survivors.
                    DB::table($p['table'])
                        ->where('category_id', $source->id)
                        ->update(['category_id' => $target->id]);
                }

                foreach (self::FK_COLUMNS as $table) {
                    DB::table($table)
                        ->where('category_id', $source->id)
                        ->update(['category_id' => $target->id]);
                }

                DB::table('categories')->where('id', $source->id)->delete();
            }
        });
    }

    public function down(): void
    {
        // Irreversible: the redundant category rows and their original
        // link rows were merged away. Re-seed if the demo split is needed.
    }
};
