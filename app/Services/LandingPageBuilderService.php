<?php

namespace App\Services;

use App\Models\LandingPage;
use App\Models\LandingPageBlock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Backing store for the landing-page drag-and-drop builder. Mirrors
 * ClinicPageBuilderService:
 *  - Seeds a sensible default block set per page type on creation.
 *  - Returns the ordered visible blocks for the public renderer (cached).
 *  - Returns the full ordered list for the admin builder.
 *  - Persists reorders.
 *
 * Unlike the clinic page builder (fixed key set), landing blocks are free-form
 * and repeatable, so this seeds *initial* blocks rather than enforcing a fixed
 * roster. The default config for each block type lives in DEFAULTS and is
 * mirrored on the React side.
 */
class LandingPageBuilderService
{
    /**
     * Default block config by type. The renderer + admin panels read these
     * keys; keep in sync with the TS block registry.
     */
    public const DEFAULTS = [
        'hero'      => ['source' => 'auto', 'heading' => null, 'subheading' => null, 'show_cta' => true],
        'services'  => ['source' => 'auto', 'item_limit' => 6, 'manual_ids' => []],
        'offers'    => ['source' => 'auto', 'item_limit' => 6, 'manual_ids' => []],
        'doctors'   => ['source' => 'auto', 'item_limit' => 6, 'manual_ids' => []],
        'gallery'   => ['source' => 'auto', 'item_limit' => 8],
        'reviews'   => ['source' => 'auto', 'item_limit' => 6],
        'faq'       => ['items' => []], // [{q, a}]
        'map'       => [],
        'booking'   => ['heading' => null],
        'countdown' => ['target' => null, 'heading' => null],
    ];

    /**
     * The default ordered block roster seeded for each page type when a page
     * is first created.
     */
    public const TYPE_DEFAULTS = [
        'clinic'     => ['hero', 'services', 'offers', 'doctors', 'gallery', 'reviews', 'faq', 'map', 'booking'],
        'offer'      => ['hero', 'countdown', 'offers', 'services', 'faq', 'booking'],
        'city'       => ['hero', 'services', 'offers', 'faq', 'booking'],
        'category'   => ['hero', 'services', 'offers', 'doctors', 'faq', 'booking'],
        'comparison' => ['hero', 'faq', 'booking'],
        'custom'     => ['hero', 'booking'],
    ];

    /**
     * Seed the default blocks for a freshly created page. Idempotent — does
     * nothing if the page already has blocks.
     */
    public function seedDefaults(LandingPage $page): void
    {
        if ($page->blocks()->exists()) {
            return;
        }

        $types = self::TYPE_DEFAULTS[$page->type] ?? self::TYPE_DEFAULTS['custom'];

        $now = now();
        $rows = [];
        foreach ($types as $i => $type) {
            $rows[] = [
                'landing_page_id' => $page->id,
                'type'            => $type,
                'sort_order'      => ($i + 1) * 10,
                'is_visible'      => true,
                'config'          => json_encode(self::DEFAULTS[$type] ?? []),
                'block_version'   => 1,
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }

        DB::table('landing_page_blocks')->insert($rows);
        Cache::forget(LandingPage::cacheKey($page->id));
    }

    /**
     * Ordered visible blocks for the public renderer. Cached per page; busted
     * on any block save/delete via the model's booted() hook.
     *
     * @return Collection<int, LandingPageBlock>
     */
    public function forPublic(int $pageId): Collection
    {
        return Cache::remember(
            LandingPage::cacheKey($pageId),
            now()->addMinutes(10),
            fn () => LandingPageBlock::where('landing_page_id', $pageId)
                ->where('is_visible', true)
                ->orderBy('sort_order')
                ->get()
        );
    }

    /**
     * Full ordered block list (visible + hidden) for the admin builder.
     *
     * @return Collection<int, LandingPageBlock>
     */
    public function forAdmin(int $pageId): Collection
    {
        return LandingPageBlock::where('landing_page_id', $pageId)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Persist a reorder of the page's blocks. $order is [{id, sort_order}, …]
     * scoped to this page so a stale/foreign id can't touch another page.
     */
    public function reorder(LandingPage $page, array $order): void
    {
        DB::transaction(function () use ($page, $order) {
            foreach ($order as $row) {
                LandingPageBlock::where('id', $row['id'])
                    ->where('landing_page_id', $page->id)
                    ->update(['sort_order' => $row['sort_order']]);
            }
        });

        Cache::forget(LandingPage::cacheKey($page->id));
    }
}
