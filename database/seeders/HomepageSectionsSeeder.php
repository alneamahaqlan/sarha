<?php

namespace Database\Seeders;

use App\Models\HomepageSection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the initial homepage layout — the 10 existing hardcoded sections
 * become CMS rows, and the 6 new sections (banner + offers + articles + 3
 * category-filtered offer strips) are inserted before "categories" exactly
 * where the spec asked.
 *
 * Idempotent: upserts by `key`, so re-running won't duplicate or reorder
 * sections an admin has since rearranged.
 */
class HomepageSectionsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $sections = [
            // ── Top of page ──────────────────────────────────────────
            ['hero',                'hero',            10, null, null],
            ['stats',               'stats',           20, null, null],

            // ── New sections (inserted before "categories") ─────────
            ['banner',              'banner',          30, null, ['interval' => 5]],
            ['offers',              'offers',          40, 8,    ['min_discount' => 10]],
            ['articles',            'articles',        50, 6,    ['only_published' => true]],
            ['category_offers_1',   'category_offers', 60, 6,    ['category_slug' => 'dentistry']],
            ['category_offers_2',   'category_offers', 70, 6,    ['category_slug' => 'dermatology']],
            ['category_offers_3',   'category_offers', 80, 6,    ['category_slug' => 'cosmetics']],

            // ── Existing categories grid + below ────────────────────
            ['categories',          'categories',      90, 14,   null],
            ['ai_highlight',        'ai_highlight',   100, null, null],
            ['how_it_works',        'how_it_works',   110, null, null],
            ['featured_clinics',    'clinic_list',    120, 8,    ['source' => 'featured']],
            ['top_rated_clinics',   'clinic_list',    130, 3,    ['source' => 'top_rated']],
            ['best_priced_clinics', 'clinic_list',    140, 3,    ['source' => 'best_priced']],
            ['map',                 'map',            150, 200,  null],
            ['cta_for_clinics',     'cta',            160, null, null],
        ];

        foreach ($sections as [$key, $type, $order, $limit, $config]) {
            DB::table('homepage_sections')->updateOrInsert(
                ['key' => $key],
                [
                    'type'                    => $type,
                    'is_active'               => true,
                    'sort_order'              => $order,
                    'item_limit'              => $limit,
                    'banner_interval_seconds' => $type === 'banner' ? 5 : null,
                    'show_on_mobile'          => true,
                    'show_on_desktop'         => true,
                    'starts_at'               => null,
                    'ends_at'                 => null,
                    'config'                  => $config ? json_encode($config, JSON_UNESCAPED_UNICODE) : null,
                    'updated_at'              => $now,
                    'created_at'              => DB::raw("COALESCE((SELECT created_at FROM homepage_sections AS hs WHERE hs.`key` = '" . addslashes($key) . "' LIMIT 1), '" . $now->toDateTimeString() . "')"),
                ]
            );
        }

        // Seed a few demo banner slides if the banner section just got created
        // and has no slides yet — gives the admin/QA something to see.
        $bannerId = DB::table('homepage_sections')->where('key', 'banner')->value('id');
        if ($bannerId && DB::table('homepage_banner_slides')->where('homepage_section_id', $bannerId)->doesntExist()) {
            DB::table('homepage_banner_slides')->insert([
                ['homepage_section_id' => $bannerId, 'image' => 'demo/banners/banner-1.jpg', 'link_url' => '/search?featured=1',         'is_active' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now],
                ['homepage_section_id' => $bannerId, 'image' => 'demo/banners/banner-2.jpg', 'link_url' => '/search?sort=cheapest',      'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['homepage_section_id' => $bannerId, 'image' => 'demo/banners/banner-3.jpg', 'link_url' => '/search?category=dentistry', 'is_active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        $this->command?->info('HomepageSectionsSeeder: 16 sections + 3 demo banner slides ready.');
    }
}
