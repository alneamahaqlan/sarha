<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\Clinic;
use App\Support\BadgeRuleRegistry;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the display badges (Badges Center) shown publicly for one or many
 * complexes, and recomputes the automatic ones.
 *
 * Render-ready shape per badge: ['key', 'label', 'icon', 'color'].
 */
class ClinicBadgeService
{
    /**
     * Active, non-expired badges for a set of complexes, filtered by where
     * they're allowed to show. Returns [clinic_id => array<badge>] — batched
     * so card lists don't N+1.
     *
     * @param  array<int>  $clinicIds
     * @param  'header'|'cards'  $placement
     * @return array<int, array<int, array{key:string,label:string,icon:string,color:string}>>
     */
    /** Request-level memo keyed by "placement:clinic_id" to avoid N+1 on card lists. */
    private static array $memo = [];

    public function forClinics(array $clinicIds, string $placement = 'header'): array
    {
        $clinicIds = array_values(array_unique(array_filter(array_map('intval', $clinicIds))));
        if (empty($clinicIds)) {
            return [];
        }

        // Only query the IDs we haven't resolved for this placement yet.
        $missing = array_values(array_filter($clinicIds, fn ($id) => ! array_key_exists("{$placement}:{$id}", self::$memo)));

        if (! empty($missing)) {
            $places = $placement === 'cards' ? ['cards', 'both'] : ['header', 'both'];

            $rows = DB::table('badge_clinic as bc')
                ->join('badges as b', 'b.id', '=', 'bc.badge_id')
                ->whereIn('bc.clinic_id', $missing)
                ->where('b.is_active', true)
                ->whereIn('b.placement', $places)
                ->where(function ($q) {
                    $q->whereNull('bc.expires_at')->orWhere('bc.expires_at', '>', now());
                })
                ->orderBy('b.sort_order')
                ->orderBy('b.id')
                ->get(['bc.clinic_id', 'b.key', 'b.label_ar', 'b.label_en', 'b.icon', 'b.color']);

            $en = app()->getLocale() === 'en';
            $built = [];
            foreach ($rows as $r) {
                $built[(int) $r->clinic_id][] = [
                    'key'   => $r->key,
                    'label' => $en ? $r->label_en : $r->label_ar,
                    'icon'  => $r->icon,
                    'color' => $r->color,
                ];
            }
            // Memo every requested id (empty array included) so misses don't re-query.
            foreach ($missing as $id) {
                self::$memo["{$placement}:{$id}"] = $built[$id] ?? [];
            }
        }

        $out = [];
        foreach ($clinicIds as $id) {
            $out[$id] = self::$memo["{$placement}:{$id}"];
        }

        return $out;
    }

    /** Card badges for a single complex (memoised). */
    public function forCard(Clinic $clinic): array
    {
        return $this->forClinics([$clinic->id], 'cards')[$clinic->id] ?? [];
    }

    /** Header badges for a single complex. */
    public function forClinic(Clinic $clinic): array
    {
        return $this->forClinics([$clinic->id], 'header')[$clinic->id] ?? [];
    }

    /**
     * Re-evaluate every active AUTO badge and replace its auto-assigned rows.
     * Manual assignments (source = 'manual') are never touched. Returns a
     * per-badge count of winners for the command's summary.
     *
     * @return array<string,int>
     */
    public function recompute(): array
    {
        $summary = [];

        $autoBadges = Badge::query()
            ->where('mode', Badge::MODE_AUTO)
            ->whereNotNull('rule_key')
            ->get();

        foreach ($autoBadges as $badge) {
            $winners = $badge->is_active
                ? BadgeRuleRegistry::resolve($badge->rule_key, $badge->rule_params ?? [])
                : collect();

            DB::transaction(function () use ($badge, $winners) {
                // Drop only this badge's previous AUTO winners.
                DB::table('badge_clinic')
                    ->where('badge_id', $badge->id)
                    ->where('source', 'auto')
                    ->delete();

                if ($winners->isEmpty()) {
                    return;
                }

                $now = now();
                $rows = $winners->map(fn ($cid) => [
                    'badge_id'   => $badge->id,
                    'clinic_id'  => $cid,
                    'source'     => 'auto',
                    'expires_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                // Skip clinics already holding this badge manually (unique key).
                $manual = DB::table('badge_clinic')
                    ->where('badge_id', $badge->id)
                    ->where('source', 'manual')
                    ->pluck('clinic_id')
                    ->all();

                $rows = array_filter($rows, fn ($r) => ! in_array($r['clinic_id'], $manual, true));

                if (! empty($rows)) {
                    DB::table('badge_clinic')->insert(array_values($rows));
                }
            });

            $summary[$badge->key] = $winners->count();
        }

        return $summary;
    }
}
