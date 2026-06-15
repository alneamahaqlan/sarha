<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Offer;
use App\Models\Service;
use App\Support\BadgeRuleRegistry;
use App\Support\BadgeTargets;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the display badges (Badges Center) shown publicly for any badgeable
 * entity (clinic / offer / service / doctor), and recomputes the automatic ones.
 *
 * Despite the legacy name, this service is polymorphic: forClinics/forCard are
 * thin wrappers over the generic forTargets(). Render-ready shape per badge:
 * ['key', 'label', 'icon', 'color', 'description'].
 */
class ClinicBadgeService
{
    /** Request-level memo keyed by "type:placement:id" to avoid N+1 on card lists. */
    private static array $memo = [];

    /**
     * Active, non-expired badges for a set of entities of one type, filtered by
     * placement. Returns [id => array<badge>] — batched so card lists don't N+1.
     *
     * @param  class-string  $type       full model class (App\Models\Clinic, …)
     * @param  array<int>    $ids
     * @param  'header'|'cards'  $placement
     * @return array<int, array<int, array{key:string,label:string,icon:string,color:string,description:?string}>>
     */
    public function forTargets(string $type, array $ids, string $placement = 'header'): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids)) {
            return [];
        }

        $missing = array_values(array_filter(
            $ids,
            fn ($id) => ! array_key_exists("{$type}:{$placement}:{$id}", self::$memo),
        ));

        if (! empty($missing)) {
            $places = $placement === 'cards' ? ['cards', 'both'] : ['header', 'both'];

            $rows = DB::table('badgeables as ba')
                ->join('badges as b', 'b.id', '=', 'ba.badge_id')
                ->where('ba.badgeable_type', $type)
                ->whereIn('ba.badgeable_id', $missing)
                ->where('b.is_active', true)
                ->whereIn('b.placement', $places)
                ->where(function ($q) {
                    $q->whereNull('ba.expires_at')->orWhere('ba.expires_at', '>', now());
                })
                ->orderBy('b.sort_order')
                ->orderBy('b.id')
                ->get(['ba.badgeable_id', 'b.key', 'b.label_ar', 'b.label_en', 'b.description_ar', 'b.description_en', 'b.icon', 'b.color']);

            $en = app()->getLocale() === 'en';
            $built = [];
            foreach ($rows as $r) {
                $built[(int) $r->badgeable_id][] = [
                    'key'         => $r->key,
                    'label'       => $en ? $r->label_en : $r->label_ar,
                    'description' => $en ? $r->description_en : $r->description_ar,
                    'icon'        => $r->icon,
                    'color'       => $r->color,
                ];
            }
            foreach ($missing as $id) {
                self::$memo["{$type}:{$placement}:{$id}"] = $built[$id] ?? [];
            }
        }

        $out = [];
        foreach ($ids as $id) {
            $out[$id] = self::$memo["{$type}:{$placement}:{$id}"];
        }

        return $out;
    }

    // ── clinic helpers (kept for the existing public blades) ────────────────

    public function forClinics(array $clinicIds, string $placement = 'header'): array
    {
        return $this->forTargets(Clinic::class, $clinicIds, $placement);
    }

    /** Card badges for a single complex (memoised). */
    public function forCard(Clinic $clinic): array
    {
        return $this->forTargets(Clinic::class, [$clinic->id], 'cards')[$clinic->id] ?? [];
    }

    /** Header badges for a single complex. */
    public function forClinic(Clinic $clinic): array
    {
        return $this->forTargets(Clinic::class, [$clinic->id], 'header')[$clinic->id] ?? [];
    }

    // ── other entity helpers ────────────────────────────────────────────────

    public function forOffer(Offer $offer, string $placement = 'cards'): array
    {
        return $this->forTargets(Offer::class, [$offer->id], $placement)[$offer->id] ?? [];
    }

    public function forService(Service $service, string $placement = 'cards'): array
    {
        return $this->forTargets(Service::class, [$service->id], $placement)[$service->id] ?? [];
    }

    public function forDoctor(Doctor $doctor, string $placement = 'cards'): array
    {
        return $this->forTargets(Doctor::class, [$doctor->id], $placement)[$doctor->id] ?? [];
    }

    /**
     * Re-evaluate every active AUTO badge and replace its auto-assigned rows for
     * the rule's target type. Manual assignments (source='manual') are never
     * touched. Returns a per-badge count of winners for the command summary.
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
            $rule = BadgeRuleRegistry::get($badge->rule_key);
            $targetClass = $rule ? BadgeTargets::classFor($rule['target_type'] ?? 'clinic') : null;

            $winners = ($badge->is_active && $rule && $targetClass)
                ? BadgeRuleRegistry::resolve($badge->rule_key, $badge->rule_params ?? [])
                : collect();

            if (! $targetClass) {
                $summary[$badge->key] = 0;
                continue;
            }

            DB::transaction(function () use ($badge, $winners, $targetClass) {
                // Drop only this badge's previous AUTO winners for this type.
                DB::table('badgeables')
                    ->where('badge_id', $badge->id)
                    ->where('badgeable_type', $targetClass)
                    ->where('source', 'auto')
                    ->delete();

                if ($winners->isEmpty()) {
                    return;
                }

                // Skip entities already holding this badge manually (unique key).
                $manual = DB::table('badgeables')
                    ->where('badge_id', $badge->id)
                    ->where('badgeable_type', $targetClass)
                    ->where('source', 'manual')
                    ->pluck('badgeable_id')
                    ->all();

                $now = now();
                $rows = $winners
                    ->reject(fn ($id) => in_array($id, $manual, true))
                    ->map(fn ($id) => [
                        'badge_id'       => $badge->id,
                        'badgeable_type' => $targetClass,
                        'badgeable_id'   => $id,
                        'source'         => 'auto',
                        'expires_at'     => null,
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ])->values()->all();

                if (! empty($rows)) {
                    DB::table('badgeables')->insert($rows);
                }
            });

            $summary[$badge->key] = $winners->count();
        }

        return $summary;
    }
}
