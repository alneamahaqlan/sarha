<?php

namespace App\Support;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Offer;
use App\Models\Service;
use Illuminate\Support\Collection;

/**
 * Registry of the entity kinds a badge can be attached to. Maps a stable
 * short alias (stored in badges.target_types, homepage config, API payloads)
 * to its Eloquent class, an Arabic label, and a search query for the manual
 * assignment picker.
 *
 * Add a new badgeable kind = one entry here (+ a `badges()` morphToMany on the
 * model). Everything downstream (admin picker, validation, homepage section)
 * reads from this map.
 */
class BadgeTargets
{
    /** @return array<string, array{class:string,label_ar:string,label_en:string}> */
    public static function types(): array
    {
        return [
            'clinic'  => ['class' => Clinic::class,  'label_ar' => 'مجمع',  'label_en' => 'Complex'],
            'offer'   => ['class' => Offer::class,   'label_ar' => 'عرض',   'label_en' => 'Offer'],
            'service' => ['class' => Service::class, 'label_ar' => 'خدمة',  'label_en' => 'Service'],
            'doctor'  => ['class' => Doctor::class,  'label_ar' => 'طبيب',  'label_en' => 'Doctor'],
        ];
    }

    public static function aliases(): array
    {
        return array_keys(self::types());
    }

    /** Resolve an alias (e.g. "offer") to its model class. */
    public static function classFor(?string $alias): ?string
    {
        return self::types()[$alias]['class'] ?? null;
    }

    /** Resolve a model class back to its alias (e.g. App\Models\Offer → "offer"). */
    public static function aliasFor(string $class): ?string
    {
        foreach (self::types() as $alias => $cfg) {
            if ($cfg['class'] === $class) {
                return $alias;
            }
        }
        return null;
    }

    /** Closure-free meta for the admin UI (type picker). */
    public static function meta(): array
    {
        return collect(self::types())
            ->map(fn ($cfg, $alias) => [
                'key'      => $alias,
                'label_ar' => $cfg['label_ar'],
                'label_en' => $cfg['label_en'],
            ])
            ->values()
            ->all();
    }

    /**
     * Search candidates of a given target type for the manual picker.
     *
     * @return Collection<int, array{id:int,name:string}>
     */
    public static function search(string $alias, string $term, int $limit = 30): Collection
    {
        $term = trim($term);

        return match ($alias) {
            'clinic' => Clinic::query()
                ->when($term !== '', fn ($q) => $q->where('name', 'like', "%{$term}%"))
                ->orderBy('name')->limit($limit)->get(['id', 'name'])
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]),

            'offer' => Offer::query()
                ->with('clinic:id,name')
                ->when($term !== '', fn ($q) => $q->where('title', 'like', "%{$term}%"))
                ->orderByDesc('id')->limit($limit)->get(['id', 'title', 'clinic_id'])
                ->map(fn ($o) => ['id' => $o->id, 'name' => trim($o->title.' — '.($o->clinic->name ?? ''), ' —')]),

            'service' => Service::query()
                ->with('clinic:id,name')
                ->where('is_catchall', false)
                ->when($term !== '', fn ($q) => $q->where('name', 'like', "%{$term}%"))
                ->orderBy('name')->limit($limit)->get(['id', 'name', 'clinic_id'])
                ->map(fn ($s) => ['id' => $s->id, 'name' => trim($s->name.' — '.($s->clinic->name ?? ''), ' —')]),

            'doctor' => Doctor::query()
                ->with('clinic:id,name')
                ->when($term !== '', fn ($q) => $q->where('name', 'like', "%{$term}%"))
                ->orderBy('name')->limit($limit)->get(['id', 'name', 'clinic_id'])
                ->map(fn ($d) => ['id' => $d->id, 'name' => trim($d->name.' — '.($d->clinic->name ?? ''), ' —')]),

            default => collect(),
        };
    }
}
