<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One-shot backfill that rebuilds clinic_categories from each clinic's
 * actual service mix. The original MassiveCityCoverageSeeder seeded
 * clinic_categories with 3–6 RANDOM tags, which left 75 % of clinics
 * untagged for the dental specialty even when they sold dental services
 * — that's BUG-06 in the QA report ("ابحث عن مجمع أسنان في الرياض"
 * returned 1 clinic out of 30).
 *
 * Safe to re-run on production: it only adds rows that don't exist yet
 * (insertOrIgnore on the (clinic_id, category_id) unique key).
 */
class BackfillClinicCategoriesFromServicesSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('clinics')->doesntExist() || DB::table('services')->doesntExist()) {
            $this->command?->warn('BackfillClinicCategoriesFromServicesSeeder: needs clinics + services.');
            return;
        }

        $now = Carbon::now()->toDateTimeString();

        // Pre-compute (clinic_id, category_id) pairs the directory SHOULD
        // have — one row per category-of-a-service-the-clinic-offers.
        $rows = DB::table('category_service')
            ->join('services', 'services.id', '=', 'category_service.service_id')
            ->selectRaw('services.clinic_id, category_service.category_id')
            ->whereNull('services.deleted_at')
            ->distinct()
            ->get();

        $existing = DB::table('clinic_categories')
            ->select('clinic_id', 'category_id')
            ->get()
            ->groupBy('clinic_id')
            ->map(fn ($g) => $g->pluck('category_id')->all());

        $missing = [];
        foreach ($rows as $r) {
            $have = $existing->get($r->clinic_id, []);
            if (! in_array($r->category_id, $have, true)) {
                $missing[] = [
                    'clinic_id'   => $r->clinic_id,
                    'category_id' => $r->category_id,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            }
        }

        if (empty($missing)) {
            $this->command?->info('BackfillClinicCategoriesFromServicesSeeder: nothing to add.');
            return;
        }

        foreach (array_chunk($missing, 500) as $chunk) {
            DB::table('clinic_categories')->insertOrIgnore($chunk);
        }

        $this->command?->info('BackfillClinicCategoriesFromServicesSeeder: added ' . count($missing) . ' clinic-category links.');
    }
}
