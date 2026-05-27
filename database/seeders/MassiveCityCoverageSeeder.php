<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Dense, per-city directory seeder. Guarantees CLINICS_PER_CITY complexes
 * in every active city, each one fully furnished (working hours, multiple
 * categories, sub-clinics, 15–30 services from a ~150-variant pool that
 * covers every specialty, 5–12 doctors, 1–4 packages, and 3–10 Google
 * reviews).
 *
 * Design rules
 *   - Per-city loop (not random) so no city is starved of data.
 *   - Additive & idempotent: each city's count is topped up to the target;
 *     existing clinics are never touched.
 *   - DB::table()->insert() only — no observers, no Faker, safe to run on
 *     production with --force.
 *   - Batched inserts (chunks of 200) so 10k+ service rows stay fast.
 *
 * Run:  php artisan db:seed --class=MassiveCityCoverageSeeder --force
 */
class MassiveCityCoverageSeeder extends Seeder
{
    /** Target complexes per active city. 21 cities × 30 = 630 complexes. */
    private const CLINICS_PER_CITY = 30;

    /** Services per clinic, drawn from SERVICE_POOL. ~22 avg × 630 = ~14k services. */
    private const SERVICES_PER_CLINIC_MIN = 15;
    private const SERVICES_PER_CLINIC_MAX = 30;

    private Carbon $now;
    private string $pw;

    /** Resolved at runtime: service_categories.slug => id. */
    private array $serviceCategoryMap = [];

    public function run(): void
    {
        $this->now = now();
        $this->pw = Hash::make('password');

        if (DB::table('cities')->count() === 0 || DB::table('categories')->count() === 0) {
            $this->command?->warn('MassiveCityCoverageSeeder: needs cities + categories first (run DemoSeeder).');
            return;
        }

        if (DB::table('service_categories')->doesntExist()) {
            $this->command?->warn('MassiveCityCoverageSeeder: needs service_categories first (run ServiceCategoriesSeeder).');
            return;
        }

        // Cache the slug → id map once for the whole seeder run so each
        // service insert is a hash lookup, not a query.
        $this->serviceCategoryMap = DB::table('service_categories')->pluck('id', 'slug')->all();
        $fallbackCategoryId = $this->serviceCategoryMap['general-consultations']
            ?? array_values($this->serviceCategoryMap)[0] ?? null;
        if ($fallbackCategoryId === null) {
            $this->command?->error('MassiveCityCoverageSeeder: service_categories table is empty.');
            return;
        }

        $cities      = DB::table('cities')->where('is_active', true)->orderBy('id')->get(['id', 'name']);
        $categoryIds = DB::table('categories')->pluck('id')->all();

        $totalAdded = 0;
        foreach ($cities as $city) {
            $existing = DB::table('clinics')->where('city_id', $city->id)->count();
            $needed   = max(0, self::CLINICS_PER_CITY - $existing);

            if ($needed === 0) {
                $this->command?->line("  · {$city->name}: {$existing} already, skipping");
                continue;
            }

            for ($i = 0; $i < $needed; $i++) {
                $this->seedOneClinic($city, $categoryIds, $totalAdded + $i);
            }
            $totalAdded += $needed;

            $this->command?->info("  ✔ {$city->name}: {$existing} existing + {$needed} new = " . ($existing + $needed));
        }

        $this->command?->info("MassiveCityCoverageSeeder: added {$totalAdded} complexes.");
        $this->command?->info("Totals — clinics: " . DB::table('clinics')->count()
            . ", services: "  . DB::table('services')->count()
            . ", doctors: "   . DB::table('doctors')->count()
            . ", packages: "  . DB::table('packages')->count());
    }

    // -----------------------------------------------------------------
    //   One clinic + its full structure (batched per clinic)
    // -----------------------------------------------------------------

    private function seedOneClinic(object $city, array $categoryIds, int $globalIndex): void
    {
        $createdDays = min(rand(0, 360), rand(0, 360));
        $created     = $this->now->copy()->subDays($createdDays);
        $plan        = $this->pick(['basic', 'premium', 'premium', 'premium']);
        $status      = $this->pick(['active', 'active', 'active', 'active', 'active', 'active', 'active', 'pending']);
        $subStart    = $this->now->copy()->subDays(rand(5, 300));
        $subEnds     = $this->now->copy()->addDays(rand(-20, 180));
        [$lat, $lng] = BackfillClinicCoordinatesSeeder::jitteredFor($city->name);
        $hasSocials  = rand(0, 1) === 1;
        $brand       = $this->pick(self::BRANDS);
        $type        = $this->pick(self::TYPES);
        $suffix      = rand(0, 2) === 0 ? ' الطبي' : (rand(0, 1) ? ' التخصصي' : '');

        $clinicId = DB::table('clinics')->insertGetId([
            'name'                   => trim("{$type} {$brand} {$globalIndex}{$suffix}"),
            'slug'                   => Str::slug("clinic-mc-{$city->id}-{$globalIndex}-" . Str::random(5)),
            'phone'                  => '05' . str_pad((string) (50000000 + $globalIndex * 17), 8, '0', STR_PAD_LEFT),
            'email'                  => "clinic-mc-{$city->id}-{$globalIndex}@demo.sa",
            'password'               => $this->pw,
            'city_id'                => $city->id,
            'district'               => $this->pick(self::DISTRICTS),
            'address'                => 'حي ' . $this->pick(self::DISTRICTS) . '، شارع ' . $this->pick(self::STREETS) . '، مبنى ' . rand(100, 999),
            'latitude'               => $lat,
            'longitude'              => $lng,
            'license_number'         => 'LIC-' . rand(100000, 999999),
            'maps_url'               => $hasSocials ? 'https://maps.app.goo.gl/' . Str::random(10) : null,
            'description'            => $this->pick(self::DESCRIPTIONS),
            'gallery'                => json_encode([]),
            'website'                => rand(0, 1) ? "https://demo-mc-{$globalIndex}.sa" : null,
            'instagram'              => $hasSocials ? "clinic_mc_{$globalIndex}" : null,
            'twitter'                => $hasSocials && rand(0, 1) ? "clinic_mc_{$globalIndex}" : null,
            'snapchat'               => $hasSocials && rand(0, 1) ? "clinic_mc_{$globalIndex}" : null,
            'tiktok'                 => $hasSocials && rand(0, 1) ? "clinic_mc_{$globalIndex}" : null,
            'status'                 => $status,
            'subscription_type'      => $plan,
            'subscription_starts_at' => $subStart->toDateTimeString(),
            'subscription_ends_at'   => $subEnds->toDateTimeString(),
            'is_featured'            => $plan === 'premium' && rand(0, 3) === 0,
            'sort_order'             => $globalIndex,
            'created_at'             => $created->toDateTimeString(),
            'updated_at'             => $this->now,
        ]);

        $this->seedWorkingHours($clinicId);
        $this->seedCategories($clinicId, $categoryIds);
        $subIds = $this->seedSubClinics($clinicId, $categoryIds);
        $this->seedServices($clinicId, $subIds, $createdDays);
        $this->seedDoctors($clinicId, $subIds);
        $this->seedPackages($clinicId);
        $this->seedReviews($clinicId);
    }

    // -----------------------------------------------------------------
    //   Per-clinic structure
    // -----------------------------------------------------------------

    private function seedWorkingHours(int $clinicId): void
    {
        $opens  = $this->pick(['08:00:00', '08:30:00', '09:00:00', '10:00:00']);
        $closes = $this->pick(['20:00:00', '21:00:00', '22:00:00', '23:00:00']);
        $rows = [];
        for ($d = 0; $d < 7; $d++) {
            $isOpen = ! ($d === 5 && rand(0, 2) === 0); // Friday sometimes closed
            $rows[] = [
                'clinic_id'   => $clinicId,
                'day_of_week' => $d,
                'is_open'     => $isOpen,
                'opens_at'    => $isOpen ? $opens : null,
                'closes_at'   => $isOpen ? $closes : null,
                'created_at'  => $this->now,
                'updated_at'  => $this->now,
            ];
        }
        DB::table('clinic_working_hours')->insert($rows);
    }

    private function seedCategories(int $clinicId, array $categoryIds): void
    {
        // 3–6 categories per clinic so EVERY category gets dense representation
        // across the platform when this seeder finishes.
        $count = rand(3, min(6, count($categoryIds)));
        $picked = collect($categoryIds)->shuffle()->take($count)->all();
        DB::table('clinic_categories')->insert(array_map(fn ($cat) => [
            'clinic_id'   => $clinicId,
            'category_id' => $cat,
            'created_at'  => $this->now,
            'updated_at'  => $this->now,
        ], $picked));
    }

    private function seedSubClinics(int $clinicId, array $categoryIds): array
    {
        $defs = collect(self::SUB_CLINICS)->shuffle()->take(rand(2, 5))->values();
        $ids = [];
        foreach ($defs as $idx => $sd) {
            $ids[] = DB::table('sub_clinics')->insertGetId([
                'clinic_id'   => $clinicId,
                'category_id' => $this->pick($categoryIds),
                'name'        => $sd[0],
                'name_en'     => $sd[1],
                'is_active'   => true,
                'sort_order'  => $idx,
                'created_at'  => $this->now,
                'updated_at'  => $this->now,
            ]);
        }
        return $ids;
    }

    /**
     * Each clinic gets a wide service mix — but every clinic also gets a
     * couple of "popular" services (laser, dental, dermatology) so a customer
     * searching for any common term sees options in every city.
     */
    private function seedServices(int $clinicId, array $subIds, int $createdDays): void
    {
        $popular = collect(self::POPULAR_SERVICES)->shuffle()->take(rand(3, 6));
        $other   = collect(self::SERVICE_POOL)->shuffle()->take(self::SERVICES_PER_CLINIC_MAX - $popular->count());
        $picked  = $popular->concat($other)->unique(fn ($s) => $s[0])
            ->take(rand(self::SERVICES_PER_CLINIC_MIN, self::SERVICES_PER_CLINIC_MAX))
            ->values();

        // Resolve the service-category slug attached to each pool entry into
        // a real id once per row — falls back to general-consultations if a
        // pool entry doesn't carry an explicit slug (shouldn't happen).
        $fallback = $this->serviceCategoryMap['general-consultations']
            ?? array_values($this->serviceCategoryMap)[0];

        $rows = [];
        foreach ($picked as $j => $svc) {
            $price    = (int) (round(rand($svc[1], $svc[2]) / 10) * 10);
            $hasOffer = rand(0, 3) === 0;
            $slug     = $svc[3] ?? null;
            $catId    = ($slug && isset($this->serviceCategoryMap[$slug])) ? $this->serviceCategoryMap[$slug] : $fallback;
            $rows[]   = [
                'clinic_id'           => $clinicId,
                'sub_clinic_id'       => $subIds && rand(0, 4) > 0 ? $this->pick($subIds) : null,
                'service_category_id' => $catId,
                'name'                => $svc[0],
                'description'         => 'خدمة طبية احترافية على يد نخبة من الأطباء وبأحدث الأجهزة.',
                'price'               => $price,
                'old_price'           => $hasOffer ? $price + rand(50, 800) : null,
                'offer_expires_at'    => $hasOffer ? $this->now->copy()->addDays(rand(3, 45))->toDateTimeString() : null,
                'is_featured_offer'   => $hasOffer,
                'is_active'           => rand(0, 14) > 0, // ~7% inactive
                'sort_order'          => $j,
                'created_at'          => $this->now->copy()->subDays(min($createdDays, rand(0, $createdDays + 1)))->toDateTimeString(),
                'updated_at'          => $this->now,
            ];
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('services')->insert($chunk);
        }
    }

    private function seedDoctors(int $clinicId, array $subIds): void
    {
        $count = rand(5, 12);
        $rows = [];
        for ($j = 0; $j < $count; $j++) {
            $female = rand(0, 1) === 1;
            $rows[] = [
                'clinic_id'        => $clinicId,
                'sub_clinic_id'    => $subIds ? $this->pick($subIds) : null,
                'name'             => ($female ? $this->pick(self::DOC_FEMALE) : $this->pick(self::DOC_MALE)) . ' ' . $this->pick(self::LAST_NAMES),
                'specialty'        => $this->pick(self::SPECIALTIES),
                'gender'           => $female ? 'female' : 'male',
                'photo'            => null,
                'bio'              => 'خبرة واسعة في تشخيص وعلاج الحالات بأحدث التقنيات الطبية.',
                'qualifications'   => 'بورد سعودي + زمالة في التخصص الدقيق.',
                'years_experience' => rand(2, 30),
                'university'       => $this->pick(self::UNIVERSITIES),
                'languages'        => $this->pick(self::LANGUAGES),
                'is_active'        => true,
                'sort_order'       => $j,
                'created_at'       => $this->now,
                'updated_at'       => $this->now,
            ];
        }
        DB::table('doctors')->insert($rows);
    }

    private function seedPackages(int $clinicId): void
    {
        $svcIds = DB::table('services')->where('clinic_id', $clinicId)->pluck('id')->all();
        if (count($svcIds) < 2) return;

        $count = rand(1, 4);
        for ($p = 0; $p < $count; $p++) {
            $price = rand(300, 4000);
            $pkgId = DB::table('packages')->insertGetId([
                'clinic_id'   => $clinicId,
                'name'        => $this->pick(self::PACKAGE_NAMES),
                'description' => 'باقة مميزة تجمع عدة خدمات بسعر موفّر لفترة محدودة.',
                'price'       => $price,
                'old_price'   => $price + rand(200, 1500),
                'expires_at'  => $this->now->copy()->addDays(rand(15, 120))->toDateString(),
                'is_active'   => true,
                'sort_order'  => $p,
                'created_at'  => $this->now,
                'updated_at'  => $this->now,
            ]);
            $bundle = collect($svcIds)->shuffle()->take(rand(2, 4))->all();
            DB::table('package_service')->insert(array_map(fn ($sid) => [
                'package_id' => $pkgId,
                'service_id' => $sid,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ], $bundle));
        }
    }

    private function seedReviews(int $clinicId): void
    {
        $count = rand(3, 10);
        $rows = [];
        for ($r = 0; $r < $count; $r++) {
            $rating = $this->pick([5, 5, 5, 5, 4, 4, 4, 4, 3, 3, 2]); // skewed positive
            $female = rand(0, 1) === 1;
            $rows[] = [
                'clinic_id'        => $clinicId,
                'google_review_id' => 'gr-mc-' . $clinicId . '-' . $r,
                'reviewer_name'    => ($female ? $this->pick(['أم محمد', 'سارة', 'نورة', 'منى', 'ريم', 'هند', 'لمى'])
                                              : $this->pick(['أبو خالد', 'فهد', 'سعود', 'محمد', 'عبدالله', 'ناصر', 'تركي'])),
                'reviewer_photo'   => null,
                'rating'           => $rating,
                'review_text'      => $this->pick(self::REVIEW_TEXTS_BY_RATING[$rating] ?? ['تجربة جيدة بشكل عام.']),
                'reviewed_at'      => $this->now->copy()->subDays(rand(5, 360))->toDateTimeString(),
                'is_visible'       => true,
                'created_at'       => $this->now,
                'updated_at'       => $this->now,
            ];
        }
        DB::table('google_reviews')->insert($rows);
    }

    // -----------------------------------------------------------------
    //   Tiny helpers
    // -----------------------------------------------------------------

    private function pick(array $a)
    {
        return $a[array_rand($a)];
    }

    // -----------------------------------------------------------------
    //   Data pools (kept as constants so each clinic share the same
    //   naming language, prices, and specialty distribution)
    // -----------------------------------------------------------------

    private const TYPES = ['مجمع', 'مركز', 'عيادات', 'مستوصف', 'مجمع عيادات', 'المركز الطبي', 'مركز طب'];

    private const BRANDS = [
        'النخبة', 'الشفاء', 'الحياة', 'العناية', 'التخصصي', 'الرعاية', 'الأمل', 'المستقبل',
        'السلامة', 'الوفاء', 'النور', 'الياسمين', 'الربيع', 'الصفوة', 'المروج', 'الواحة',
        'اللؤلؤة', 'الماسة', 'الخليج', 'الأطلس', 'السدرة', 'البيان', 'الإتقان', 'الميزان',
        'الدانة', 'الفؤاد', 'الرحمة', 'الجوهرة', 'التميز', 'الراقي', 'الواثق', 'البلسم',
        'الصحة', 'النخبة الطبية', 'الزهور', 'الفجر', 'العافية', 'الأماني', 'الأطباء',
    ];

    private const DISTRICTS = [
        'الملز', 'العليا', 'النخيل', 'الورود', 'النزهة', 'الياسمين', 'الربيع', 'حطين',
        'الملقا', 'السليمانية', 'المروج', 'الروضة', 'الصحافة', 'القدس', 'غرناطة',
        'الفيحاء', 'النسيم', 'الشاطئ', 'الكورنيش', 'الفيصلية', 'الأندلس', 'البساتين',
    ];

    private const STREETS = [
        'الملك فهد', 'العليا', 'التحلية', 'الأمير سلطان', 'الملك عبدالعزيز', 'الأمير محمد بن عبدالعزيز',
        'الستين', 'العروبة', 'مكة المكرمة', 'الإمام الشافعي', 'الخليج', 'الواحة',
    ];

    private const DESCRIPTIONS = [
        'مجمع طبي متكامل يضم أحدث الأجهزة وأمهر الكوادر الطبية لتقديم خدمة استثنائية لكل مريض.',
        'مركز طبي يجمع بين الخبرة العالمية والمعايير المحلية، مع فريق متخصص في أرقى التخصصات.',
        'وجهتك الطبية الموثوقة لتشخيص دقيق وعلاج فعّال في بيئة مريحة وآمنة.',
        'نقدم رعاية صحية متميزة بأحدث التقنيات وأعلى معايير الجودة، مع تجربة مريض مميزة من أول زيارة.',
        'مجمع طبي معتمد يقدم خدمات شاملة في تخصصات عدة، مع التزام بالجودة والسلامة.',
    ];

    private const SUB_CLINICS = [
        ['قسم الأسنان', 'Dental'], ['قسم الجلدية', 'Dermatology'], ['قسم العيون', 'Ophthalmology'],
        ['القسم العام', 'General'], ['الفرع النسائي', 'Women'], ['قسم الأطفال', 'Pediatrics'],
        ['قسم العظام', 'Orthopedics'], ['قسم التجميل', 'Cosmetic'], ['قسم التغذية', 'Nutrition'],
        ['قسم الأنف والأذن والحنجرة', 'ENT'], ['قسم القلب', 'Cardiology'],
        ['قسم الباطنة', 'Internal Medicine'], ['قسم العلاج الطبيعي', 'Physical Therapy'],
        ['قسم المختبر', 'Lab'], ['قسم الأشعة', 'Radiology'],
    ];

    /**
     * Popular services — sampled MORE often so every clinic has at least one
     * laser, dental, dermatology service. Drives coverage for the most-
     * searched terms.
     */
    /**
     * Popular services — sampled MORE often so every clinic has at least
     * one laser, dental, dermatology service. Each entry now carries its
     * canonical service-category slug as a 4th element so the row's
     * service_category_id is set correctly at insert time.
     */
    private const POPULAR_SERVICES = [
        ['تنظيف الأسنان',           150, 400,  'cleaning-polishing'],
        ['ليزر إزالة الشعر (جلسة)', 200, 900,  'laser-hair-removal'],
        ['كشف جلدية',                150, 400,  'dermatology-consults'],
        ['كشف عام',                  100, 300,  'general-consultations'],
        ['تحليل دم شامل',            150, 600,  'lab-tests'],
        ['كشف أطفال',                150, 400,  'pediatrics-vaccinations'],
    ];

    /**
     * The "wide" pool — service variants across every specialty so the search
     * returns realistic, varied results across clinics + cities.
     * Each entry: [name, min_price, max_price, service_category_slug]
     */
    private const SERVICE_POOL = [
        // ───── DENTISTRY ─────
        ['تنظيف الأسنان',         150, 400,   'cleaning-polishing'],
        ['تبييض الأسنان',         600, 1500,  'teeth-whitening'],
        ['تبييض زوم',             1500, 3500, 'teeth-whitening'],
        ['حشوة تجميلية',          200, 500,   'dental-fillings'],
        ['حشوة عصب',              600, 1500,  'dental-fillings'],
        ['زراعة سن',              2500, 6000, 'dental-implants'],
        ['زراعة فوريّة',          4000, 9000, 'dental-implants'],
        ['تقويم معدني',           5000, 12000, 'orthodontics'],
        ['تقويم شفاف (دامون)',    9000, 20000, 'orthodontics'],
        ['تقويم انفزلاين',        15000, 30000, 'orthodontics'],
        ['علاج عصب',              600, 1500,  'root-canal'],
        ['خلع ضرس العقل',         500, 1500,  'extraction-oral-surgery'],
        ['ابتسامة هوليوود (فينير)', 6000, 18000, 'hollywood-smile-veneers'],
        ['عدسات الأسنان (لومينير)', 8000, 22000, 'hollywood-smile-veneers'],
        ['كشف أسنان',             80, 250,    'general-consultations'],
        ['تركيب طقم متحرك',       1500, 4000, 'dental-prosthetics'],
        ['تركيب طقم ثابت',        4500, 12000, 'dental-prosthetics'],

        // ───── DERMATOLOGY + COSMETIC + LASER ─────
        ['كشف جلدية',                  150, 400,  'dermatology-consults'],
        ['تقشير كيميائي',              400, 1200, 'peeling-brightening'],
        ['تقشير الكريستال',            350, 1000, 'peeling-brightening'],
        ['حقن بوتكس',                  800, 2500, 'botox-filler-injections'],
        ['حقن فيلر شفايف',             1200, 3000, 'botox-filler-injections'],
        ['حقن فيلر خدود',              1500, 4000, 'botox-filler-injections'],
        ['ميزوثيرابي للوجه',           500, 1500, 'mesotherapy'],
        ['ميزوثيرابي للشعر',           400, 1200, 'mesotherapy'],
        ['نضارة وتفتيح',               500, 1800, 'peeling-brightening'],
        ['تنظيف بشرة عميق',            250, 800,  'facials-skincare'],
        ['علاج حب الشباب (جلسة)',      300, 900,  'dermatology-consults'],
        ['ليزر إزالة الشعر (جلسة)',    200, 900,  'laser-hair-removal'],
        ['ليزر إزالة الشعر — وجه',     150, 500,  'laser-hair-removal'],
        ['ليزر إزالة الشعر — ساقين',   300, 900,  'laser-hair-removal'],
        ['ليزر إزالة الشعر — جسم كامل', 800, 2200, 'laser-hair-removal'],
        ['ليزر فراكشنال',              600, 1800, 'skin-resurfacing-laser'],
        ['ليزر كاربون (هوليوود فيشل)', 500, 1500, 'skin-resurfacing-laser'],
        ['ليزر الندبات',               600, 1700, 'skin-resurfacing-laser'],
        ['نحت الجسم بالتبريد (كولتك)', 1000, 4000, 'body-contouring'],
        ['شد الوجه بالخيوط',           1500, 5000, 'body-contouring'],
        ['شد الجسم بالراديو فريكونسي', 800, 2500, 'body-contouring'],

        // ───── OPHTHALMOLOGY ─────
        ['فحص نظر شامل',         150, 400,   'eye-exams'],
        ['عملية ليزك',           4000, 9000, 'vision-correction-lasik'],
        ['عملية فيمتوليزك',      6000, 12000, 'vision-correction-lasik'],
        ['عدسات لاصقة طبية',     300, 900,   'eye-exams'],
        ['كشف عيون',             100, 300,   'eye-exams'],
        ['علاج جفاف العين',      250, 800,   'eye-surgeries-diseases'],
        ['فحص قاع العين',        200, 600,   'eye-exams'],
        ['عملية المياه البيضاء', 5000, 14000, 'eye-surgeries-diseases'],

        // ───── PEDIATRICS ─────
        ['كشف أطفال',            150, 400, 'pediatrics-vaccinations'],
        ['تطعيمات',              100, 600, 'pediatrics-vaccinations'],
        ['متابعة حديثي الولادة', 200, 600, 'pediatrics-vaccinations'],
        ['استشارة رضاعة',        200, 500, 'pediatrics-vaccinations'],
        ['تقييم نمو طفل',        250, 700, 'pediatrics-vaccinations'],

        // ───── GYN + WOMEN ─────
        ['كشف نساء وولادة',  200, 600,   'gynecology-obstetrics'],
        ['متابعة حمل',       250, 700,   'gynecology-obstetrics'],
        ['سونار رباعي الأبعاد', 500, 1500, 'gynecology-obstetrics'],
        ['فحص هرمونات',      300, 900,   'gynecology-obstetrics'],
        ['تنظيف رحم',        1000, 3000, 'gynecology-obstetrics'],
        ['تركيب لولب',       500, 1500,  'gynecology-obstetrics'],
        ['استشارة عقم',      400, 1200,  'gynecology-obstetrics'],

        // ───── ORTHO ─────
        ['كشف عظام',                200, 500,   'orthopedics-joints'],
        ['حقن مفصل بالكورتيزون',    400, 1500,  'orthopedics-joints'],
        ['حقن بلازما PRP للركبة',   1000, 2800, 'orthopedics-joints'],
        ['أشعة عظام',               200, 600,   'orthopedics-joints'],
        ['جبس / تجبير',             300, 1000,  'orthopedics-joints'],

        // ───── CARDIO ─────
        ['كشف قلب',              200, 500,  'cardiology'],
        ['تخطيط قلب',            150, 500,  'cardiology'],
        ['إيكو القلب',           400, 1200, 'cardiology'],
        ['اختبار جهد قلبي',      500, 1500, 'cardiology'],
        ['هولتر قلب 24 ساعة',    600, 1800, 'cardiology'],

        // ───── INTERNAL MED ─────
        ['كشف عام',              100, 300,  'general-consultations'],
        ['كشف باطنية',           150, 500,  'internal-medicine'],
        ['متابعة سكري',          200, 600,  'internal-medicine'],
        ['متابعة ضغط',           200, 600,  'internal-medicine'],
        ['فحص شامل سنوي',        600, 1800, 'internal-medicine'],

        // ───── ENT ─────
        ['كشف أنف وأذن وحنجرة',  150, 400, 'ent'],
        ['غسيل أذن',             100, 300, 'ent'],
        ['اختبار سمع',           200, 600, 'ent'],
        ['جلسة بخار',            50, 200,  'ent'],

        // ───── PSYCHIATRY ─────
        ['كشف نفسي',                300, 800,  'mental-health'],
        ['جلسة علاج نفسي',          400, 1200, 'mental-health'],
        ['متابعة دوائية نفسية',     250, 700,  'mental-health'],

        // ───── NUTRITION ─────
        ['استشارة تغذية',            200, 600,  'nutrition-weight-loss'],
        ['برنامج تنحيف شهري',        800, 3000, 'nutrition-weight-loss'],
        ['برنامج اكتساب وزن',        600, 2000, 'nutrition-weight-loss'],
        ['تحليل دقيق للجسم (InBody)', 100, 300,  'nutrition-weight-loss'],

        // ───── PHYSICAL THERAPY ─────
        ['جلسة علاج طبيعي',       150, 500, 'physical-therapy-rehab'],
        ['علاج طبيعي للظهر',      200, 600, 'physical-therapy-rehab'],
        ['جلسة كهرباء (TENS)',    100, 350, 'physical-therapy-rehab'],
        ['تأهيل ما بعد العمليات', 300, 800, 'physical-therapy-rehab'],
        ['تدليك علاجي',           200, 600, 'physical-therapy-rehab'],

        // ───── LABS ─────
        ['تحليل دم شامل',           150, 600,  'lab-tests'],
        ['تحليل بول كامل',          50, 200,   'lab-tests'],
        ['تحليل وظائف كبد',         100, 400,  'lab-tests'],
        ['تحليل وظائف كلى',         100, 400,  'lab-tests'],
        ['تحليل سكر تراكمي HbA1c',  80, 300,   'lab-tests'],
        ['تحليل فيتامين د',         100, 350,  'lab-tests'],
        ['تحليل غدة درقية TSH',     100, 350,  'lab-tests'],
        ['تحليل هرمونات أنثوية',    300, 900,  'lab-tests'],
        ['تحليل حساسية شامل',       600, 2000, 'lab-tests'],

        // ───── RADIOLOGY ─────
        ['أشعة سينية',           150, 500,  'radiology-imaging'],
        ['أشعة مقطعية (CT)',     700, 2200, 'radiology-imaging'],
        ['رنين مغناطيسي (MRI)',  800, 2500, 'radiology-imaging'],
        ['ماموجرام',             400, 1200, 'radiology-imaging'],
        ['سونار بطن',            300, 900,  'radiology-imaging'],
        ['دوبلر شرايين',         500, 1500, 'radiology-imaging'],

        // ───── UROLOGY ─────
        ['كشف مسالك',            200, 600,  'urology'],
        ['تحليل سائل منوي',      200, 600,  'urology'],
        ['تفتيت حصوة بالليزر',   3000, 8000, 'urology'],

        // ───── GENERAL SURGERY ─────
        ['كشف جراحة عامة', 200, 500,  'general-surgery'],
        ['إزالة كيس دهني', 800, 2500, 'general-surgery'],
        ['ختان أطفال',     600, 1800, 'general-surgery'],
    ];

    private const DOC_MALE = [
        'د. أحمد', 'د. خالد', 'د. محمد', 'د. عبدالله', 'د. سعود', 'د. فهد',
        'د. ناصر', 'د. ماجد', 'د. يوسف', 'د. تركي', 'د. بدر', 'د. وليد',
        'د. عمر', 'د. سلطان', 'د. عبدالرحمن', 'د. مهند', 'د. زياد',
    ];

    private const DOC_FEMALE = [
        'د. سارة', 'د. نورة', 'د. ريم', 'د. منى', 'د. هند', 'د. لمى',
        'د. دانة', 'د. جواهر', 'د. شهد', 'د. العنود', 'د. أمل', 'د. مها',
        'د. أسماء', 'د. روان', 'د. وفاء',
    ];

    private const LAST_NAMES = [
        'العتيبي', 'الزهراني', 'القحطاني', 'الشمري', 'الدوسري', 'الحربي',
        'الغامدي', 'المالكي', 'السبيعي', 'المطيري', 'العنزي', 'الرشيدي',
        'الشهري', 'البقمي', 'الأحمدي', 'العسيري', 'الجهني', 'العمري',
    ];

    private const SPECIALTIES = [
        'استشاري جراحة عامة', 'أخصائي أسنان', 'استشاري جلدية', 'أخصائي عيون',
        'استشاري نساء وولادة', 'أخصائي أطفال', 'استشاري عظام', 'أخصائي باطنية',
        'استشاري تجميل', 'أخصائي تغذية علاجية', 'استشاري قلب', 'استشاري أنف وأذن وحنجرة',
        'أخصائي نفسي', 'استشاري مسالك', 'أخصائي علاج طبيعي', 'استشاري أشعة',
    ];

    private const UNIVERSITIES = [
        'جامعة الملك سعود', 'جامعة الملك عبدالعزيز', 'جامعة الملك فيصل',
        'جامعة القاهرة', 'جامعة الأردن', 'جامعة الإمام', 'جامعة الأمير سلطان',
        'جامعة عين شمس', 'جامعة الإسكندرية',
    ];

    private const LANGUAGES = [
        'العربية، الإنجليزية', 'العربية', 'العربية، الإنجليزية، الأردية',
        'العربية، الإنجليزية، الفرنسية', 'العربية، الإنجليزية، الفلبينية',
    ];

    private const PACKAGE_NAMES = [
        'باقة العناية المتكاملة', 'باقة الابتسامة', 'باقة الفحص الشامل',
        'باقة التجميل', 'باقة الأمومة', 'باقة النضارة', 'باقة الرعاية الأسبوعية',
        'باقة العائلة', 'باقة ما قبل الزواج', 'باقة الصحة الإنجابية',
    ];

    private const REVIEW_TEXTS_BY_RATING = [
        5 => [
            'تجربة ممتازة، استقبال راقي وطاقم محترف جداً. أنصح به بشدة.',
            'الخدمة أكثر من رائعة، التزام بالمواعيد ودكتور يشرح كل شيء بهدوء.',
            'مكان نظيف وحديث، وكشف وعلاج بأعلى مستوى. شكراً لكل الفريق.',
            'الأسعار معقولة جداً مقارنة بالخدمة المقدمة. تجربة فاقت توقعاتي.',
        ],
        4 => [
            'تجربة جيدة بشكل عام، الاستقبال محترم والخدمة سريعة.',
            'الطبيب ممتاز، فقط الانتظار شوي طويل لكن النتيجة تستحق.',
            'مكان مرتب وموظفين متعاونين، أنصح فيه.',
        ],
        3 => [
            'الخدمة عادية، نظيف لكن في تأخير بالمواعيد.',
            'تجربة وسط، الطبيب جيد لكن الاستقبال يحتاج تحسين.',
        ],
        2 => [
            'الانتظار كان طويل والخدمة دون التوقع.',
            'تجربة لم ترقَ للمستوى، أتمنى تحسين التواصل.',
        ],
    ];
}
