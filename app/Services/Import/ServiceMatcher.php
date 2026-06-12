<?php

namespace App\Services\Import;

use App\Models\Service;
use App\Services\CatalogMatchService;
use Illuminate\Support\Collection;

/**
 * Matches a free-text service name from an imported sheet against THIS
 * CLINIC'S OWN services (every booking links to a clinic Service, which
 * carries its sub-clinic). Distinct from CatalogMatchService, which matches
 * the global canonical catalog — here we only ever want the clinic's rows.
 *
 * Reuses CatalogMatchService::normalize() for Arabic folding (alef/ya/ta,
 * diacritics, leading "ال", whitespace) so behaviour is consistent with the
 * manual catalog flow, then layers a similarity score on top:
 *   - exact match after normalisation        → auto-linked (no confirmation)
 *   - similarity >= SUGGEST_THRESHOLD         → suggested, needs confirmation
 *   - below                                   → unmatched, no suggestion
 */
class ServiceMatcher
{
    /** Percent similarity (0–100) at/above which we surface a suggestion. */
    public const SUGGEST_THRESHOLD = 80;

    /** Max suggestions returned per unmatched name. */
    private const MAX_SUGGESTIONS = 3;

    public function __construct(private readonly CatalogMatchService $catalog) {}

    /**
     * Analyse the distinct service names found in the sheet against the
     * clinic's services.
     *
     * @param  array<int,string>  $names  raw service-name cell values
     * @return array<int,array{
     *     name:string,
     *     status:string,
     *     service:?array{id:int,name:string,sub_clinic_id:?int,sub_clinic_name:?string},
     *     suggestions:array<int,array{service_id:int,name:string,sub_clinic_name:?string,score:int}>
     * }>
     */
    public function analyze(int $clinicId, array $names): array
    {
        $services = $this->clinicServices($clinicId);

        // Pre-compute the normalised name of every clinic service once.
        $normalizedServices = $services->map(fn (Service $s) => [
            'service'    => $s,
            'normalized' => $this->catalog->normalize((string) $s->name),
        ]);

        $distinct = collect($names)
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->values();

        return $distinct->map(function (string $name) use ($normalizedServices) {
            $target = $this->catalog->normalize($name);

            // 1. Exact (post-normalisation) → auto-link.
            $exact = $normalizedServices->first(fn ($row) => $row['normalized'] !== '' && $row['normalized'] === $target);
            if ($exact) {
                return [
                    'name'        => $name,
                    'status'      => 'matched',
                    'service'     => $this->serviceArray($exact['service']),
                    'suggestions' => [],
                ];
            }

            // 2. Rank by similarity for suggestions.
            $suggestions = $normalizedServices
                ->map(function ($row) use ($target) {
                    similar_text($target, $row['normalized'], $percent);
                    return ['service' => $row['service'], 'score' => (int) round($percent)];
                })
                ->filter(fn ($row) => $row['score'] >= self::SUGGEST_THRESHOLD)
                ->sortByDesc('score')
                ->take(self::MAX_SUGGESTIONS)
                ->map(fn ($row) => [
                    'service_id'      => $row['service']->id,
                    'name'            => $row['service']->name,
                    'sub_clinic_name' => $row['service']->subClinic?->name,
                    'score'           => $row['score'],
                ])
                ->values()
                ->all();

            return [
                'name'        => $name,
                'status'      => 'unmatched',
                'service'     => null,
                'suggestions' => $suggestions,
            ];
        })->all();
    }

    /** The clinic's services (active + inactive) with their sub-clinic. */
    private function clinicServices(int $clinicId): Collection
    {
        return Service::query()
            ->where('clinic_id', $clinicId)
            ->with('subClinic:id,name')
            ->get(['id', 'clinic_id', 'sub_clinic_id', 'name']);
    }

    /** @return array{id:int,name:string,sub_clinic_id:?int,sub_clinic_name:?string} */
    private function serviceArray(Service $s): array
    {
        return [
            'id'              => $s->id,
            'name'            => $s->name,
            'sub_clinic_id'   => $s->sub_clinic_id,
            'sub_clinic_name' => $s->subClinic?->name,
        ];
    }
}
