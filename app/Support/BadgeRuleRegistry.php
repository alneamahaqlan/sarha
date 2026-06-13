<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Clinic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Code registry of AUTOMATIC badge rule types. Each rule resolves to the set
 * of clinic IDs that currently "win" it, given the badge's stored params.
 *
 * Add a new automatic badge type by adding ONE entry here — the admin then
 * creates a badge in the Badges Center, binds it to this rule key, sets the
 * label / icon / colour, and `badges:recompute` does the rest. This mirrors
 * the ClinicGateRegistry "register once → works everywhere" pattern.
 */
class BadgeRuleRegistry
{
    /**
     * @return array<string, array{label_ar:string,label_en:string,default_params:array,resolve:callable}>
     */
    public static function rules(): array
    {
        return [
            'most_booked' => [
                'label_ar'       => 'الأكثر حجزًا',
                'label_en'       => 'Most booked',
                'default_params' => ['window_days' => 30, 'top_n' => 5, 'min' => 1],
                'resolve'        => fn (array $p): Collection => Booking::query()
                    ->where('created_at', '>=', now()->subDays((int) ($p['window_days'] ?? 30)))
                    ->whereIn('clinic_id', self::visibleIds())
                    ->selectRaw('clinic_id, COUNT(*) c')
                    ->groupBy('clinic_id')
                    ->havingRaw('COUNT(*) >= ?', [(int) ($p['min'] ?? 1)])
                    ->orderByDesc('c')
                    ->limit((int) ($p['top_n'] ?? 5))
                    ->pluck('clinic_id'),
            ],

            'fastest_growing' => [
                'label_ar'       => 'الأسرع نموًا',
                'label_en'       => 'Fastest growing',
                'default_params' => ['window_days' => 7, 'top_n' => 5, 'min' => 1],
                'resolve'        => fn (array $p): Collection => DB::table('clinic_follows')
                    ->where('created_at', '>=', now()->subDays((int) ($p['window_days'] ?? 7)))
                    ->whereIn('clinic_id', self::visibleIds())
                    ->selectRaw('clinic_id, COUNT(*) c')
                    ->groupBy('clinic_id')
                    ->havingRaw('COUNT(*) >= ?', [(int) ($p['min'] ?? 1)])
                    ->orderByDesc('c')
                    ->limit((int) ($p['top_n'] ?? 5))
                    ->pluck('clinic_id'),
            ],

            'most_followed' => [
                'label_ar'       => 'الأكثر متابعة',
                'label_en'       => 'Most followed',
                'default_params' => ['top_n' => 5, 'min' => 1],
                'resolve'        => fn (array $p): Collection => DB::table('clinic_follows')
                    ->whereIn('clinic_id', self::visibleIds())
                    ->selectRaw('clinic_id, COUNT(*) c')
                    ->groupBy('clinic_id')
                    ->havingRaw('COUNT(*) >= ?', [(int) ($p['min'] ?? 1)])
                    ->orderByDesc('c')
                    ->limit((int) ($p['top_n'] ?? 5))
                    ->pluck('clinic_id'),
            ],

            'top_rated' => [
                'label_ar'       => 'الأعلى تقييمًا',
                'label_en'       => 'Top rated',
                'default_params' => ['top_n' => 5, 'min_rating' => 4.5, 'min_reviews' => 5],
                // Filtered in PHP to stay independent of SQL HAVING-on-alias modes.
                'resolve'        => fn (array $p): Collection => Clinic::query()
                    ->publiclyVisible()
                    ->withAvg('googleReviews', 'rating')
                    ->withCount('googleReviews')
                    ->get(['id'])
                    ->filter(fn ($c) => (int) $c->google_reviews_count >= (int) ($p['min_reviews'] ?? 5)
                        && (float) ($c->google_reviews_avg_rating ?? 0) >= (float) ($p['min_rating'] ?? 4.5))
                    ->sortByDesc('google_reviews_avg_rating')
                    ->take((int) ($p['top_n'] ?? 5))
                    ->pluck('id')
                    ->values(),
            ],
        ];
    }

    public static function get(string $key): ?array
    {
        return self::rules()[$key] ?? null;
    }

    /** Resolve a rule to its winning clinic IDs (merges defaults with stored params). */
    public static function resolve(string $key, array $params = []): Collection
    {
        $rule = self::get($key);
        if (! $rule) {
            return collect();
        }

        $merged = array_merge($rule['default_params'], array_filter($params, fn ($v) => $v !== null && $v !== ''));

        return collect($rule['resolve']($merged))->map(fn ($id) => (int) $id)->values();
    }

    /** Closure-free metadata for the admin UI (rule dropdown + default params). */
    public static function meta(): array
    {
        return collect(self::rules())->map(fn ($r, $key) => [
            'key'            => $key,
            'label_ar'       => $r['label_ar'],
            'label_en'       => $r['label_en'],
            'default_params' => $r['default_params'],
        ])->values()->all();
    }

    private static function visibleIds(): Collection
    {
        return Clinic::query()->publiclyVisible()->pluck('id');
    }
}
