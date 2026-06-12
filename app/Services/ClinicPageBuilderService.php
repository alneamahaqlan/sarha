<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\ClinicPageSection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Page Builder backing store for the public /clinic/{slug} page.
 *
 * Responsibilities:
 *  - Lazily seed the 14 default section rows the first time a clinic's
 *    builder is touched (so the X existing clinics don't need a backfill
 *    migration — their pages keep rendering identically).
 *  - Return the active+ordered section list for the public renderer.
 *  - Return the full (active + hidden) list for the clinic admin UI.
 *  - Resolve the localized display title for a given section, honoring the
 *    admin's overrides and falling back to the site lang file.
 *  - Enforce the "protected keys can't be hidden" invariant.
 */
class ClinicPageBuilderService
{
    /**
     * The default lang-file key used for each section's heading when the
     * admin hasn't supplied an override. Mirrors the labels already living
     * in lang/{ar,en}/site.php so the public page stays consistent.
     */
    public const DEFAULT_TITLE_KEYS = [
        'hero'             => null,                    // hero has no heading
        'offers'           => 'site.tab_offers',
        'packages'         => 'site.tab_packages',
        'services'         => 'site.tab_services',
        'sub_clinics'      => 'site.tab_clinics',
        'doctors'          => 'site.tab_doctors',
        'before_after'     => 'site.tab_before_after',
        'google_reviews'   => 'site.tab_reviews',
        'articles'         => 'site.tab_articles',
        'about'            => 'site.tab_about',
        'faqs'             => 'site.faqs_title',
        'contact_info'     => 'site.contact_info',
        'working_hours'    => 'site.working_hours_title',
        'social_links'     => 'site.social_links_title',
        'similar_services' => 'site.similar_services_title',
        'floating_ctas'    => null,                    // no heading either
    ];

    /**
     * Get the full (active + hidden) ordered section list for the admin UI.
     * Lazily seeds defaults on first call so every clinic always has the 14
     * rows present.
     *
     * @return Collection<int, ClinicPageSection>
     */
    public function forAdmin(int $clinicId): Collection
    {
        $this->ensureSeeded($clinicId);

        return ClinicPageSection::where('clinic_id', $clinicId)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Get the ordered active-only sections, keyed by section `key`, for the
     * public renderer. Cached per clinic (busted on save/delete).
     *
     * @return Collection<string, ClinicPageSection>
     */
    public function forPublic(int $clinicId): Collection
    {
        return Cache::remember(
            ClinicPageSection::cacheKey($clinicId),
            now()->addMinutes(10),
            function () use ($clinicId) {
                $this->ensureSeeded($clinicId);

                return ClinicPageSection::where('clinic_id', $clinicId)
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get()
                    ->keyBy('key');
            }
        );
    }

    /**
     * Resolve the section's display title. Order of precedence:
     *  1. Locale-specific admin override (title_ar / title_en).
     *  2. The other-locale admin override (so an Arabic-only override still
     *     shows on the English page rather than falling back to the lang key
     *     a clinic admin probably never wanted to override).
     *  3. The site.php default lang key.
     *  4. Empty string if there is no default (hero / floating_ctas).
     */
    public function titleFor(ClinicPageSection $section, ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        $primary   = $locale === 'en' ? $section->title_en : $section->title_ar;
        $secondary = $locale === 'en' ? $section->title_ar : $section->title_en;

        if ($primary)   { return $primary; }
        if ($secondary) { return $secondary; }

        $defaultKey = self::DEFAULT_TITLE_KEYS[$section->key] ?? null;

        return $defaultKey ? (string) __($defaultKey) : '';
    }

    /**
     * True if the given key cannot be hidden via the admin panel (still
     * reorderable, but `is_active` must stay true).
     */
    public function isProtectedKey(string $key): bool
    {
        return in_array($key, ClinicPageSection::PROTECTED_KEYS, true);
    }

    /**
     * Ensure all 14 default rows exist for a clinic. Idempotent — only
     * inserts the missing keys, leaves any admin-edited rows untouched.
     */
    public function ensureSeeded(int $clinicId): void
    {
        $existingKeys = ClinicPageSection::where('clinic_id', $clinicId)
            ->pluck('key')
            ->all();

        $missing = array_values(array_diff(ClinicPageSection::KEYS, $existingKeys));
        if (empty($missing)) {
            return;
        }

        // Compute the next sort_order so newly-added defaults land at the
        // end of any pre-existing custom ordering, then number the rest in
        // the canonical default order.
        $maxSortOrder = (int) ClinicPageSection::where('clinic_id', $clinicId)->max('sort_order');

        $now = now();
        $rows = [];
        foreach ($missing as $i => $key) {
            $defaultIndex = array_search($key, ClinicPageSection::KEYS, true);
            $rows[] = [
                'clinic_id'  => $clinicId,
                'key'        => $key,
                'is_active'  => true,
                // Initial install: use the canonical 1..14 order multiplied
                // by 10 so reorders have room to splice between rows.
                // Late seed (e.g. new key added in a future deploy):
                // append after the admin's existing order.
                'sort_order' => empty($existingKeys)
                    ? ($defaultIndex + 1) * 10
                    : $maxSortOrder + ($i + 1) * 10,
                'title_ar'   => null,
                'title_en'   => null,
                'item_limit' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('clinic_page_sections')->insert($rows);
        Cache::forget(ClinicPageSection::cacheKey($clinicId));
    }

    /**
     * Backfill rows for ALL clinics. Used by an artisan command after a
     * deploy that introduces the feature; the public page still works
     * without this (lazy seed handles it) but a one-shot upfront seed makes
     * the admin UI populated for everyone immediately.
     */
    public function seedAllClinics(): int
    {
        $count = 0;
        Clinic::query()->select('id')->chunkById(200, function ($chunk) use (&$count) {
            foreach ($chunk as $clinic) {
                $this->ensureSeeded($clinic->id);
                $count++;
            }
        });
        return $count;
    }
}
