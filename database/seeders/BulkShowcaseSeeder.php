<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Dense, diverse showcase dataset: grows the directory to TARGET_CLINICS complexes,
 * each fully structured (working hours, categories, sub-clinics, dozens of services,
 * doctors, packages), then delegates to YearOfUsageSeeder to fill a year of usage
 * (stats, bookings, quotes, reviews, subscriptions, …) across every complex.
 *
 *  - Additive & idempotent: stops once TARGET_CLINICS is reached.
 *  - DB::table()->insert() only (no observers / Faker) — production-safe.
 *  - Run:  php artisan db:seed --class=BulkShowcaseSeeder --force
 */
class BulkShowcaseSeeder extends Seeder
{
    private const TARGET_CLINICS = 134; // 20 base + 114 new

    private Carbon $now;
    private string $pw;

    public function run(): void
    {
        $this->now = now();
        $this->pw = Hash::make('password');

        if (DB::table('cities')->count() === 0 || DB::table('categories')->count() === 0) {
            $this->command?->warn('BulkShowcaseSeeder: run DemoSeeder first (needs cities + categories).');
            return;
        }

        $this->seedClinicsWithStructure();

        // Fill a year of usage across the (now larger) directory.
        $this->call(YearOfUsageSeeder::class);

        $this->command?->info('BulkShowcaseSeeder: directory scaled to '
            . DB::table('clinics')->count() . ' complexes with dense data.');
    }

    // ---------- helpers ----------

    private function pick(array $a)
    {
        return $a[array_rand($a)];
    }

    private function phone(int $seed): string
    {
        return '05' . str_pad((string) (10000000 + $seed), 8, '0', STR_PAD_LEFT);
    }

    private function dayAgo(int $max = 365): int
    {
        return min(rand(0, $max), rand(0, $max)); // recency bias
    }

    private function at(int $daysAgo): string
    {
        return $this->now->copy()->subDays($daysAgo)->toDateTimeString();
    }

    // ---------- clinics + structure ----------

    private function seedClinicsWithStructure(): void
    {
        $existing = DB::table('clinics')->count();
        if ($existing >= self::TARGET_CLINICS) {
            return;
        }

        $cityIds = DB::table('cities')->pluck('id')->all();
        $cityNames = DB::table('cities')->pluck('name', 'id'); // id => Arabic name (for accurate coords)
        $categoryIds = DB::table('categories')->pluck('id')->all();

        $types = ['مجمع', 'مركز', 'عيادات', 'مستوصف', 'مجمع عيادات', 'المركز الطبي'];
        $brands = ['النخبة', 'الشفاء', 'الحياة', 'العناية', 'التخصصي', 'الرعاية', 'الأمل', 'المستقبل',
            'السلامة', 'الوفاء', 'النور', 'الياسمين', 'الربيع', 'الصفوة', 'المروج', 'الواحة', 'اللؤلؤة',
            'الماسة', 'الخليج', 'الأطلس', 'السدرة', 'البيان', 'الإتقان', 'الميزان', 'الدانة', 'الفؤاد',
            'الرحمة', 'الجوهرة', 'التميز', 'الراقي'];
        $districts = ['الملز', 'العليا', 'النخيل', 'الورود', 'النزهة', 'الياسمين', 'الربيع', 'حطين',
            'الملقا', 'السليمانية', 'المروج', 'الروضة', 'الصحافة', 'القدس', 'غرناطة'];
        $statuses = ['active', 'active', 'active', 'active', 'active', 'active', 'active', 'pending', 'suspended', 'rejected'];

        // service pool: [name, minPrice, maxPrice]
        $servicePool = [
            ['تنظيف الأسنان', 150, 400], ['تبييض الأسنان', 600, 1500], ['حشوة تجميلية', 200, 500],
            ['زراعة سن', 2500, 6000], ['تقويم معدني', 5000, 12000], ['تقويم شفاف', 9000, 20000],
            ['علاج عصب', 600, 1500], ['خلع ضرس العقل', 500, 1500], ['ابتسامة هوليوود', 6000, 18000],
            ['كشف جلدية', 150, 400], ['تقشير كيميائي', 400, 1200], ['حقن بوتكس', 800, 2500],
            ['حقن فيلر', 900, 3000], ['ليزر إزالة الشعر (جلسة)', 200, 900], ['نضارة وتفتيح', 500, 1800],
            ['فحص نظر شامل', 150, 400], ['عملية ليزك', 4000, 9000], ['عدسات لاصقة طبية', 300, 900],
            ['كشف عام', 100, 300], ['تحليل دم شامل', 150, 600], ['أشعة سينية', 150, 500],
            ['رنين مغناطيسي', 800, 2500], ['تخطيط قلب', 150, 500], ['كشف أطفال', 150, 400],
            ['تطعيمات', 100, 600], ['كشف عظام', 200, 500], ['جلسة علاج طبيعي', 150, 500],
            ['حقن مفصل', 400, 1500], ['استشارة تغذية', 200, 600], ['برنامج تنحيف شهري', 800, 3000],
            ['جلسة تنظيف بشرة', 250, 800], ['ميزوثيرابي للشعر', 400, 1200], ['نحت الجسم بالتبريد', 1000, 4000],
        ];

        $subNames = [['قسم الأسنان', 'Dental'], ['قسم الجلدية', 'Dermatology'], ['قسم العيون', 'Ophthalmology'],
            ['القسم العام', 'General'], ['الفرع النسائي', 'Women'], ['قسم الأطفال', 'Pediatrics'],
            ['قسم العظام', 'Orthopedics'], ['قسم التجميل', 'Cosmetic'], ['قسم التغذية', 'Nutrition']];

        $docM = ['د. أحمد', 'د. خالد', 'د. محمد', 'د. عبدالله', 'د. سعود', 'د. فهد', 'د. ناصر', 'د. ماجد', 'د. يوسف', 'د. تركي', 'د. بدر', 'د. وليد'];
        $docF = ['د. سارة', 'د. نورة', 'د. ريم', 'د. منى', 'د. هند', 'د. لمى', 'د. دانة', 'د. جواهر', 'د. شهد', 'د. العنود'];
        $last = ['العتيبي', 'الزهراني', 'القحطاني', 'الشمري', 'الدوسري', 'الحربي', 'الغامدي', 'المالكي', 'السبيعي', 'المطيري', 'العنزي', 'الرشيدي', 'الشهري', 'البقمي'];
        $specs = ['استشاري جراحة عامة', 'أخصائي أسنان', 'استشاري جلدية', 'أخصائي عيون', 'استشاري نساء وولادة',
            'أخصائي أطفال', 'استشاري عظام', 'أخصائي باطنية', 'استشاري تجميل', 'أخصائي تغذية علاجية'];
        $universities = ['جامعة الملك سعود', 'جامعة الملك عبدالعزيز', 'جامعة الملك فيصل', 'جامعة القاهرة', 'جامعة الأردن', 'جامعة الإمام'];
        $langs = ['العربية، الإنجليزية', 'العربية', 'العربية، الإنجليزية، الأردية'];
        $packageNames = ['باقة العناية المتكاملة', 'باقة الابتسامة', 'باقة الفحص الشامل', 'باقة التجميل', 'باقة الأمومة', 'باقة النضارة'];

        for ($i = $existing; $i < self::TARGET_CLINICS; $i++) {
            $status = $this->pick($statuses);
            $plan = $status === 'active' ? $this->pick(['basic', 'premium', 'premium']) : $this->pick(['basic', 'premium', null]);
            $createdDays = $this->dayAgo(360);
            $start = $plan ? $this->now->copy()->subDays(rand(5, 300)) : null;
            $ends = $plan ? $this->now->copy()->addDays(rand(-30, 120)) : null;
            $city = $this->pick($cityIds);
            [$lat, $lng] = BackfillClinicCoordinatesSeeder::jitteredFor($cityNames[$city] ?? null);
            $hasSocials = rand(0, 1) === 1;

            $clinicId = DB::table('clinics')->insertGetId([
                'name'                   => $this->pick($types) . ' ' . $this->pick($brands) . (rand(0, 1) ? ' الطبي' : ''),
                'slug'                   => Str::slug('clinic-' . $i . '-' . Str::random(6)),
                'phone'                  => $this->phone(2000000 + $i),
                'email'                  => 'clinic-b' . $i . '@demo.sa',
                'password'               => $this->pw,
                'city_id'                => $city,
                'district'               => $this->pick($districts),
                'address'                => 'حي ' . $this->pick($districts) . '، شارع ' . $this->pick(['الملك فهد', 'العليا', 'التحلية', 'الأمير سلطان']) . '، مبنى ' . rand(100, 999),
                'latitude'               => $lat,
                'longitude'              => $lng,
                'license_number'         => rand(0, 1) ? 'LIC-' . rand(100000, 999999) : null,
                'maps_url'               => $hasSocials ? 'https://maps.app.goo.gl/' . Str::random(10) : null,
                'description'            => 'مجمع طبي متخصص يقدم أفضل الخدمات الطبية بأحدث التقنيات وأمهر الكوادر، مع تجربة مريض متميّزة.',
                'gallery'                => json_encode([]),
                'website'                => rand(0, 1) ? 'https://demo-clinic-b' . $i . '.sa' : null,
                'instagram'              => $hasSocials ? 'clinic_b' . $i : null,
                'twitter'                => $hasSocials && rand(0, 1) ? 'clinic_b' . $i : null,
                'snapchat'               => $hasSocials && rand(0, 1) ? 'clinic_b' . $i : null,
                'tiktok'                 => $hasSocials && rand(0, 1) ? 'clinic_b' . $i : null,
                'status'                 => $status,
                'subscription_type'      => $plan,
                'subscription_starts_at' => $start?->toDateTimeString(),
                'subscription_ends_at'   => $ends?->toDateTimeString(),
                'is_featured'            => $plan === 'premium' && rand(0, 2) === 0,
                'sort_order'             => $i,
                'created_at'             => $this->at($createdDays),
                'updated_at'             => $this->now,
            ]);

            // --- working hours (varied) ---
            $opens = $this->pick(['08:00:00', '09:00:00', '10:00:00']);
            $closes = $this->pick(['21:00:00', '22:00:00', '23:00:00']);
            $hours = [];
            for ($d = 0; $d < 7; $d++) {
                $open = ! ($d === 5 && rand(0, 1)); // Friday sometimes closed
                $hours[] = [
                    'clinic_id' => $clinicId, 'day_of_week' => $d, 'is_open' => $open,
                    'opens_at' => $open ? $opens : null, 'closes_at' => $open ? $closes : null,
                    'created_at' => $this->now, 'updated_at' => $this->now,
                ];
            }
            DB::table('clinic_working_hours')->insert($hours);

            // --- categories (2-4) ---
            $cats = collect($categoryIds)->shuffle()->take(rand(2, min(4, count($categoryIds))))->all();
            DB::table('clinic_categories')->insert(array_map(fn ($cat) => [
                'clinic_id' => $clinicId, 'category_id' => $cat,
                'created_at' => $this->now, 'updated_at' => $this->now,
            ], $cats));

            // --- sub-clinics (2-4) ---
            $subDefs = collect($subNames)->shuffle()->take(rand(2, 4))->values();
            $subIds = [];
            foreach ($subDefs as $idx => $sd) {
                $subIds[] = DB::table('sub_clinics')->insertGetId([
                    'clinic_id' => $clinicId, 'category_id' => $this->pick($categoryIds),
                    'name' => $sd[0], 'name_en' => $sd[1], 'is_active' => true,
                    'sort_order' => $idx, 'created_at' => $this->now, 'updated_at' => $this->now,
                ]);
            }

            // --- services (dozens: 10-24) ---
            // Inserted row-by-row so we can capture each id and attach
            // standalone Offer rows for the discounted ones.
            $chosen = collect($servicePool)->shuffle()->take(rand(10, 24))->values();
            $offerRows = [];
            foreach ($chosen as $j => $svc) {
                $price = (int) (round(rand($svc[1], $svc[2]) / 10) * 10);
                $hasOffer = rand(0, 2) === 0;
                $serviceId = DB::table('services')->insertGetId([
                    'clinic_id'         => $clinicId,
                    'sub_clinic_id'     => rand(0, 4) > 0 && $subIds ? $this->pick($subIds) : null,
                    'name'              => $svc[0],
                    'description'       => 'خدمة طبية احترافية على يد نخبة من الأطباء بأحدث الأجهزة.',
                    'price'             => $price,
                    'is_active'         => rand(0, 12) > 0, // ~8% inactive
                    'sort_order'        => $j,
                    'created_at'        => $this->at(min($createdDays, $this->dayAgo($createdDays ?: 1))),
                    'updated_at'        => $this->now,
                ]);
                if ($hasOffer) {
                    $offerRows[] = [
                        'clinic_id'   => $clinicId,
                        'service_id'  => $serviceId,
                        'type'        => 'service',
                        'title'       => $svc[0],
                        'description' => null,
                        'image'       => null,
                        'old_price'   => $price + rand(50, 800),
                        'price'       => $price,
                        'starts_at'   => $this->now->copy()->subDays(rand(0, 7))->toDateTimeString(),
                        'ends_at'     => $this->now->copy()->addDays(rand(2, 40))->toDateTimeString(),
                        'is_featured' => rand(0, 3) === 0,
                        'is_active'   => true,
                        'sort_order'  => 0,
                        'created_at'  => $this->now,
                        'updated_at'  => $this->now,
                    ];
                }
            }
            if (! empty($offerRows)) {
                DB::table('offers')->insert($offerRows);
            }

            // --- doctors (3-8) ---
            $docRows = [];
            $dn = rand(3, 8);
            for ($j = 0; $j < $dn; $j++) {
                $female = rand(0, 1) === 1;
                $docRows[] = [
                    'clinic_id'        => $clinicId,
                    'sub_clinic_id'    => $subIds ? $this->pick($subIds) : null,
                    'name'             => ($female ? $this->pick($docF) : $this->pick($docM)) . ' ' . $this->pick($last),
                    'specialty'        => $this->pick($specs),
                    'gender'           => $female ? 'female' : 'male',
                    'photo'            => null,
                    'bio'              => 'خبرة واسعة في تشخيص وعلاج الحالات بأحدث التقنيات الطبية.',
                    'qualifications'   => 'بورد سعودي، زمالة في التخصص الدقيق.',
                    'years_experience' => rand(2, 30),
                    'university'       => $this->pick($universities),
                    'languages'        => $this->pick($langs),
                    'is_active'        => true,
                    'sort_order'       => $j,
                    'created_at'       => $this->now,
                    'updated_at'       => $this->now,
                ];
            }
            DB::table('doctors')->insert($docRows);

            // --- packages (1-3) bundling 2-3 services ---
            $svcIds = DB::table('services')->where('clinic_id', $clinicId)->pluck('id')->all();
            if (count($svcIds) >= 2) {
                $pkgN = rand(1, 3);
                for ($p = 0; $p < $pkgN; $p++) {
                    $price = rand(300, 3000);
                    $pkgId = DB::table('packages')->insertGetId([
                        'clinic_id'   => $clinicId,
                        'name'        => $this->pick($packageNames),
                        'description' => 'باقة مميزة تجمع عدة خدمات بسعر موفّر لفترة محدودة.',
                        'price'       => $price,
                        'old_price'   => $price + rand(150, 1200),
                        'expires_at'  => $this->now->copy()->addDays(rand(10, 90))->toDateString(),
                        'is_active'   => true,
                        'sort_order'  => $p,
                        'created_at'  => $this->now,
                        'updated_at'  => $this->now,
                    ]);
                    $bundle = collect($svcIds)->shuffle()->take(rand(2, 3))->all();
                    DB::table('package_service')->insert(array_map(fn ($sid) => [
                        'package_id' => $pkgId, 'service_id' => $sid,
                        'created_at' => $this->now, 'updated_at' => $this->now,
                    ], $bundle));
                }
            }
        }
    }
}
