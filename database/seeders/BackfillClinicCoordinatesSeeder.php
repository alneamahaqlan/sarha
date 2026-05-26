<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Gives every complex map coordinates that actually match its city, so the
 * homepage/search maps and the Directions button work. Fills complexes with
 * NULL coordinates and repositions any whose coordinates fall far outside their
 * city (e.g. older random demo data). Already-correct rows are left untouched.
 *
 * Run:  php artisan db:seed --class=BackfillClinicCoordinatesSeeder --force
 */
class BackfillClinicCoordinatesSeeder extends Seeder
{
    /** Approximate city centres [lat, lng], keyed by the Arabic city name. */
    public const CENTERS = [
        'الرياض'          => [24.7136, 46.6753],
        'جدة'             => [21.4858, 39.1925],
        'مكة المكرمة'     => [21.3891, 39.8579],
        'المدينة المنورة' => [24.5247, 39.5692],
        'الدمام'          => [26.4207, 50.0888],
        'الخبر'           => [26.2794, 50.2083],
        'الظهران'         => [26.2361, 50.0393],
        'الطائف'          => [21.2854, 40.4198],
        'أبها'            => [18.2164, 42.5053],
        'بريدة'           => [26.3590, 43.9817],
        'تبوك'            => [28.3835, 36.5662],
        'جازان'           => [16.8894, 42.5611],
        'نجران'           => [17.4933, 44.1277],
        'حائل'            => [27.5219, 41.6907],
        'الجبيل'          => [27.0046, 49.6606],
        'ينبع'            => [24.0883, 38.0582],
        'الأحساء'         => [25.3833, 49.5870],
        'عرعر'            => [30.9753, 41.0381],
        'سكاكا'           => [29.9697, 40.2000],
        'الباحة'          => [20.0129, 41.4677],
    ];

    /** City centre + a small jitter (~±5 km) so markers don't stack. */
    public static function jitteredFor(?string $cityName): array
    {
        [$lat, $lng] = self::CENTERS[$cityName] ?? self::CENTERS['الرياض'];
        return [
            round($lat + rand(-500, 500) / 10000, 6),
            round($lng + rand(-500, 500) / 10000, 6),
        ];
    }

    public function run(): void
    {
        $cityNames = DB::table('cities')->pluck('name', 'id'); // id => Arabic name
        $fixed = 0;

        DB::table('clinics')->select('id', 'city_id', 'latitude', 'longitude')
            ->orderBy('id')->chunk(200, function ($clinics) use ($cityNames, &$fixed) {
                foreach ($clinics as $c) {
                    $name = $cityNames[$c->city_id] ?? null;
                    [$lat, $lng] = self::CENTERS[$name] ?? self::CENTERS['الرياض'];

                    // Skip rows already near their city centre (≈ within 0.5°).
                    $hasCoords = $c->latitude !== null && $c->longitude !== null;
                    if ($hasCoords
                        && abs((float) $c->latitude - $lat) <= 0.5
                        && abs((float) $c->longitude - $lng) <= 0.5) {
                        continue;
                    }

                    [$nlat, $nlng] = self::jitteredFor($name);
                    DB::table('clinics')->where('id', $c->id)
                        ->update(['latitude' => $nlat, 'longitude' => $nlng, 'updated_at' => now()]);
                    $fixed++;
                }
            });

        $this->command?->info("BackfillClinicCoordinatesSeeder: set/fixed coordinates for {$fixed} complexes.");
    }
}
