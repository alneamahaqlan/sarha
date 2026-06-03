<?php

namespace App\Services;

use App\Models\CatalogService;
use Illuminate\Support\Str;

/**
 * Matches a free-text service name against the unified catalog so a clinic
 * service links to an existing canonical entry instead of forking a new one.
 *
 * Phase 2 is intentionally deterministic (normalise → exact → fuzzy LIKE) so
 * it works with zero AI configured. Phase 3 layers AI similarity on top for
 * the harder cases; this class stays the safe fallback either way.
 */
class CatalogMatchService
{
    /**
     * Find the single best active catalog entry for a typed service name, or
     * null when nothing is close enough to auto-link.
     */
    public function match(string $name): ?CatalogService
    {
        $normalized = $this->normalize($name);
        if ($normalized === '') {
            return null;
        }

        // 1. Exact match on the normalised name, English name, or any alias.
        $candidates = CatalogService::active()->get(['id', 'name', 'name_en', 'aliases', 'slug', 'category_id']);
        foreach ($candidates as $c) {
            if ($this->normalize($c->name) === $normalized
                || ($c->name_en && $this->normalize($c->name_en) === $normalized)
                || $this->aliasesMatch($c, fn (string $a) => $a === $normalized)) {
                return $c;
            }
        }

        // 2. Substring containment either direction — handles "تنظيف اسنان"
        //    vs "تنظيف الأسنان" once both are normalised (ال + diacritics gone).
        //    Checked against the canonical name and every alias.
        foreach ($candidates as $c) {
            $cn = $this->normalize($c->name);
            if ($cn !== '' && (str_contains($cn, $normalized) || str_contains($normalized, $cn))) {
                return $c;
            }
            if ($this->aliasesMatch($c, fn (string $a) => str_contains($a, $normalized) || str_contains($normalized, $a))) {
                return $c;
            }
        }

        return null;
    }

    /**
     * Lightweight suggestion list for a catalog picker / typeahead. Returns up
     * to $limit active entries whose normalised name overlaps the query.
     *
     * @return \Illuminate\Support\Collection<int,CatalogService>
     */
    public function suggest(string $query, int $limit = 10)
    {
        $normalized = $this->normalize($query);

        return CatalogService::active()
            ->get(['id', 'name', 'name_en', 'aliases', 'slug', 'category_id'])
            ->filter(function (CatalogService $c) use ($normalized) {
                if ($normalized === '') {
                    return true;
                }
                $cn = $this->normalize($c->name);
                if (str_contains($cn, $normalized) || str_contains($normalized, $cn)) {
                    return true;
                }
                return $this->aliasesMatch($c, fn (string $a) => str_contains($a, $normalized) || str_contains($normalized, $a));
            })
            ->take($limit)
            ->values();
    }

    /**
     * True when any of the catalog entry's aliases (normalised) satisfies the
     * given predicate. Safe when the column is null/empty.
     *
     * @param  callable(string):bool  $predicate  Receives the normalised alias.
     */
    private function aliasesMatch(CatalogService $c, callable $predicate): bool
    {
        foreach ((array) ($c->aliases ?? []) as $alias) {
            $a = $this->normalize((string) $alias);
            if ($a !== '' && $predicate($a)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fold an Arabic/English service name to a comparable key: trim, lowercase,
     * strip the definite article "ال", drop Arabic diacritics + tatweel,
     * normalise alef/ya/ta-marbuta variants, and collapse whitespace.
     */
    public function normalize(string $value): string
    {
        $s = trim(Str::lower($value));

        // Remove Arabic diacritics (harakat) and tatweel.
        $s = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $s);

        // Unify common Arabic letter variants.
        $s = strtr($s, [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا',
            'ى' => 'ي', 'ة' => 'ه',
        ]);

        // Drop a leading definite article on each word ("الاسنان" → "اسنان").
        $s = preg_replace('/(^|\s)ال/u', '$1', $s);

        // Collapse non-alphanumeric (keep Arabic + latin + digits) to spaces.
        $s = preg_replace('/[^\p{Arabic}\p{L}\p{N}]+/u', ' ', $s);

        return trim(preg_replace('/\s+/u', ' ', $s));
    }
}
