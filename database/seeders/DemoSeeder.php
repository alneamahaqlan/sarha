<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Fills every table with ~20+ demo rows for staging / preview.
 *
 * Design notes:
 *  - Uses DB::table()->insert() (NOT Eloquent) so model observers do NOT fire
 *    (no fake notifications) and there are no mass-assignment surprises.
 *  - No Faker dependency — works on a production install (composer --no-dev).
 *  - Idempotent via count-guards: each section tops the table up toward a target
 *    count, so re-running converges instead of duplicating.
 *
 * Run:  php artisan db:seed --class=DemoSeeder --force
 */
class DemoSeeder extends Seeder
{
    private string $pw;
    private \Illuminate\Support\Carbon $now;

    public function run(): void
    {
        $this->pw  = Hash::make('password');
        $this->now = now();

        $this->seedCities();
        $this->seedCategories();
        $this->seedAdmins();
        $this->seedUsers();
        $this->seedClinics();
        $this->seedClinicChildren();   // working hours, sub-clinics, category pivot
        $this->seedServices();
        $this->seedFeaturedOffers();
        $this->seedDoctors();
        $this->seedPackages();
        $this->seedArticles();
        $this->seedBookings();
        $this->seedPriceQuotes();
        $this->seedGoogleReviews();
        $this->seedSubscriptions();
        $this->seedClinicStats();
        $this->seedAiConversations();
        $this->seedOtpCodes();
        $this->seedSalesLeads();
        $this->seedComplaints();
        $this->seedFavorites();
        $this->seedAuditLogs();
        $this->seedNotifications();

        $this->command?->info('DemoSeeder: every table now holds ≥20 demo rows.');
    }

    // ---------- helpers ----------

    private function ts(int $daysAgo = 0): string
    {
        return $this->now->copy()->subDays($daysAgo)->toDateTimeString();
    }

    private function phone(int $seed): string
    {
        return '05' . str_pad((string) (10000000 + $seed), 8, '0', STR_PAD_LEFT);
    }

    /** @return array<int,int> */
    private function ids(string $table): array
    {
        return DB::table($table)->pluck('id')->all();
    }

    private function pick(array $arr)
    {
        return $arr[array_rand($arr)];
    }

    // ---------- sections ----------

    private function seedCities(): void
    {
        $cities = [
            ['الرياض', 'Riyadh'], ['جدة', 'Jeddah'], ['مكة المكرمة', 'Makkah'],
            ['المدينة المنورة', 'Madinah'], ['الدمام', 'Dammam'], ['الخبر', 'Khobar'],
            ['الظهران', 'Dhahran'], ['الطائف', 'Taif'], ['أبها', 'Abha'],
            ['بريدة', 'Buraidah'], ['تبوك', 'Tabuk'], ['جازان', 'Jazan'],
            ['نجران', 'Najran'], ['حائل', 'Hail'], ['الجبيل', 'Jubail'],
            ['ينبع', 'Yanbu'], ['الأحساء', 'Al Ahsa'], ['عرعر', 'Arar'],
            ['سكاكا', 'Sakaka'], ['الباحة', 'Al Bahah'],
        ];
        $sort = 0;
        foreach ($cities as [$ar, $en]) {
            DB::table('cities')->updateOrInsert(
                ['name' => $ar],
                ['name_en' => $en, 'is_active' => true, 'sort_order' => $sort++, 'created_at' => $this->now, 'updated_at' => $this->now],
            );
        }
    }

    private function seedCategories(): void
    {
        $cats = [
            ['أسنان', 'Dentistry', '🦷'], ['جلدية', 'Dermatology', '🧴'], ['عيون', 'Ophthalmology', '👁️'],
            ['أطفال', 'Pediatrics', '🧒'], ['عظام', 'Orthopedics', '🦴'], ['قلب', 'Cardiology', '❤️'],
            ['باطنة', 'Internal Medicine', '🩺'], ['نساء وولادة', 'Obstetrics', '🤰'], ['أنف وأذن وحنجرة', 'ENT', '👂'],
            ['مسالك بولية', 'Urology', '🧫'], ['نفسية', 'Psychiatry', '🧠'], ['تغذية', 'Nutrition', '🥗'],
            ['أعصاب', 'Neurology', '⚡'], ['جراحة عامة', 'General Surgery', '🔪'], ['أشعة', 'Radiology', '📷'],
            ['مختبر', 'Laboratory', '🧪'], ['تجميل', 'Cosmetics', '💉'], ['طب أسرة', 'Family Medicine', '👨‍👩‍👧'],
            ['سمنة', 'Obesity', '⚖️'], ['علاج طبيعي', 'Physiotherapy', '🦾'],
        ];
        $sort = 0;
        foreach ($cats as [$ar, $en, $emoji]) {
            DB::table('categories')->updateOrInsert(
                ['slug' => Str::slug($en)],
                ['name' => $ar, 'name_en' => $en, 'emoji' => $emoji, 'is_active' => true, 'sort_order' => $sort++, 'created_at' => $this->now, 'updated_at' => $this->now],
            );
        }
    }

    private function seedAdmins(): void
    {
        $target = 20;
        $existing = DB::table('admins')->count();
        $roles = ['admin', 'admin', 'sales']; // enum: super_admin|admin|sales
        for ($i = $existing; $i < $target; $i++) {
            DB::table('admins')->insert([
                'name'       => 'مدير تجريبي ' . ($i + 1),
                'email'      => 'admin' . ($i + 1) . '@saerha.sa',
                'password'   => $this->pw,
                'role'       => $roles[$i % count($roles)],
                'is_active'  => true,
                'created_at' => $this->ts(rand(1, 200)),
                'updated_at' => $this->now,
            ]);
        }
    }

    private function seedUsers(): void
    {
        $target = 25;
        $existing = DB::table('users')->count();
        $names = ['أحمد محمد', 'فاطمة علي', 'خالد عبدالله', 'نورة سعد', 'سلطان فهد', 'ريم ناصر', 'عبدالرحمن يوسف', 'هند سالم', 'ماجد تركي', 'لمى وليد'];
        for ($i = $existing; $i < $target; $i++) {
            DB::table('users')->insert([
                'name'       => $this->pick($names) . ' ' . ($i + 1),
                'email'      => null,
                'phone'      => $this->phone(200000 + $i),
                'is_active'  => true,
                'created_at' => $this->ts(rand(1, 300)),
                'updated_at' => $this->now,
            ]);
        }
    }

    private function seedClinics(): void
    {
        $target = 20;
        $existing = DB::table('clinics')->count();
        if ($existing >= $target) return;

        $cityIds = $this->ids('cities');
        $types   = ['مجمع', 'مركز', 'عيادات', 'مستوصف'];
        $specs   = ['الرياض الطبي', 'النخبة', 'الشفاء', 'الحياة', 'العناية', 'التخصصي', 'الرعاية', 'الأمل', 'المستقبل', 'السلامة', 'الوفاء', 'النور', 'الياسمين', 'الربيع', 'المركز الأول'];
        $statuses = ['active', 'active', 'active', 'pending', 'suspended'];
        $plans    = ['basic', 'premium', null];

        for ($i = $existing; $i < $target; $i++) {
            $name = $this->pick($types) . ' ' . $this->pick($specs) . ' ' . ($i + 1);
            $status = $statuses[$i % count($statuses)];
            $plan = $status === 'active' ? $this->pick(['basic', 'premium']) : $this->pick($plans);
            $start = $plan ? $this->now->copy()->subDays(rand(5, 60)) : null;
            $ends = $plan ? $this->now->copy()->addDays(rand(-5, 80)) : null;

            DB::table('clinics')->insert([
                'name'                   => $name,
                'slug'                   => Str::slug('clinic-' . $i . '-' . Str::random(5)),
                'phone'                  => $this->phone(300000 + $i),
                'email'                  => 'clinic' . ($i + 1) . '@demo.sa',
                'password'               => $this->pw,
                'city_id'                => $this->pick($cityIds),
                'address'                => 'حي الملز، شارع الملك فهد، مبنى ' . rand(100, 999),
                'latitude'               => 24.0 + (rand(0, 5000) / 1000),
                'longitude'              => 46.0 + (rand(0, 5000) / 1000),
                'description'            => 'مجمع طبي متخصص يقدم أفضل الخدمات الطبية بأحدث التقنيات وأمهر الكوادر.',
                'gallery'                => json_encode([]),
                'website'                => 'https://demo-clinic-' . ($i + 1) . '.sa',
                'instagram'              => '@clinic' . ($i + 1),
                'status'                 => $status,
                'subscription_type'      => $plan,
                'subscription_starts_at' => $start?->toDateTimeString(),
                'subscription_ends_at'   => $ends?->toDateTimeString(),
                'is_featured'            => $plan === 'premium' && rand(0, 1) === 1,
                'sort_order'             => $i,
                'created_at'             => $this->ts(rand(10, 300)),
                'updated_at'             => $this->now,
            ]);
        }
    }

    private function seedClinicChildren(): void
    {
        $clinicIds = $this->ids('clinics');
        $categoryIds = $this->ids('categories');

        foreach ($clinicIds as $cid) {
            // Working hours: 7 days (Friday closed)
            if (! DB::table('clinic_working_hours')->where('clinic_id', $cid)->exists()) {
                $rows = [];
                for ($d = 0; $d < 7; $d++) {
                    $open = $d !== 5; // 5 = Friday closed
                    $rows[] = [
                        'clinic_id'   => $cid,
                        'day_of_week' => $d,
                        'is_open'     => $open,
                        'opens_at'    => $open ? '09:00:00' : null,
                        'closes_at'   => $open ? '22:00:00' : null,
                        'created_at'  => $this->now,
                        'updated_at'  => $this->now,
                    ];
                }
                DB::table('clinic_working_hours')->insert($rows);
            }

            // category pivot (2 per clinic)
            if (! DB::table('clinic_categories')->where('clinic_id', $cid)->exists()) {
                $picked = (array) array_rand(array_flip($categoryIds), 2);
                foreach ($picked as $catId) {
                    DB::table('clinic_categories')->insertOrIgnore([
                        'clinic_id' => $cid, 'category_id' => $catId,
                        'created_at' => $this->now, 'updated_at' => $this->now,
                    ]);
                }
            }

            // sub-clinics (2 per clinic)
            if (! DB::table('sub_clinics')->where('clinic_id', $cid)->exists()) {
                foreach (['القسم الرئيسي', 'الفرع النسائي'] as $idx => $sc) {
                    DB::table('sub_clinics')->insert([
                        'clinic_id' => $cid, 'category_id' => $this->pick($categoryIds),
                        'name' => $sc, 'name_en' => $idx === 0 ? 'Main' : 'Women', 'is_active' => true,
                        'sort_order' => $idx, 'created_at' => $this->now, 'updated_at' => $this->now,
                    ]);
                }
            }
        }
    }

    private function seedServices(): void
    {
        if (DB::table('services')->count() >= 40) return;
        $clinicIds = $this->ids('clinics');
        // Sub-clinics grouped by clinic — services are distributed among them.
        $subsByClinic = DB::table('sub_clinics')->get(['id', 'clinic_id'])->groupBy('clinic_id');
        $names = ['تنظيف الأسنان', 'تبييض الأسنان', 'حشوة تجميلية', 'كشف عام', 'تقشير البشرة', 'فيلر', 'بوتكس', 'فحص نظر', 'عدسات لاصقة', 'جلسة ليزر', 'تحليل دم شامل', 'أشعة سينية', 'استشارة تغذية', 'جلسة علاج طبيعي', 'تركيب تقويم'];

        foreach ($clinicIds as $cid) {
            $count = DB::table('services')->where('clinic_id', $cid)->count();
            if ($count >= 3) continue;
            $subs = $subsByClinic[$cid] ?? collect();
            for ($j = $count; $j < 3; $j++) {
                // First two services go to the two sub-clinics; the third stays
                // "general" (no sub-clinic) so we exercise that UI bucket too.
                $sub = ($j < 2 && $subs->isNotEmpty()) ? $subs[$j % $subs->count()] : null;
                $price = rand(100, 2000);
                $hasOffer = rand(0, 2) === 0;
                DB::table('services')->insert([
                    'clinic_id'        => $cid,
                    'sub_clinic_id'    => $sub?->id,
                    'name'             => $this->pick($names),
                    'description'      => 'خدمة طبية احترافية على يد نخبة من الأطباء.',
                    'price'            => $price,
                    'old_price'         => $hasOffer ? $price + rand(50, 500) : null,
                    'offer_expires_at'  => $hasOffer ? $this->now->copy()->addDays(rand(2, 20))->toDateTimeString() : null,
                    'is_featured_offer' => $hasOffer,
                    'is_active'         => true,
                    'sort_order'        => $j,
                    'created_at'        => $this->ts(rand(1, 120)),
                    'updated_at'        => $this->now,
                ]);
            }
        }
    }

    /** Flag discounted services as "featured offers" so the offers tab has demo data. */
    private function seedFeaturedOffers(): void
    {
        DB::table('services')
            ->whereNotNull('old_price')
            ->where('is_featured_offer', false)
            ->update(['is_featured_offer' => true]);
    }

    private function seedDoctors(): void
    {
        $this->backfillDoctorDetails();
        if (DB::table('doctors')->count() >= 24) return;
        $specialties = ['استشاري جراحة عامة', 'أخصائي أسنان', 'استشاري جلدية', 'أخصائي عيون', 'استشاري نساء وولادة', 'أخصائي أطفال', 'استشاري عظام', 'أخصائي باطنية'];
        $first = ['د. أحمد', 'د. سارة', 'د. خالد', 'د. نورة', 'د. محمد', 'د. ريم', 'د. عبدالله', 'د. منى'];
        $last  = ['العتيبي', 'الزهراني', 'القحطاني', 'الشمري', 'الدوسري', 'الحربي', 'الغامدي', 'المالكي'];
        $female = ['د. سارة', 'د. نورة', 'د. ريم', 'د. منى'];
        $universities = ['جامعة الملك سعود', 'جامعة الملك عبدالعزيز', 'جامعة الملك فيصل', 'جامعة القاهرة', 'جامعة الأردن'];
        $langs = ['العربية، الإنجليزية', 'العربية', 'العربية، الإنجليزية، الأردية'];
        $subsByClinic = DB::table('sub_clinics')->get(['id', 'clinic_id'])->groupBy('clinic_id');

        foreach ($this->ids('clinics') as $cid) {
            if (DB::table('doctors')->where('clinic_id', $cid)->exists()) continue;
            $subs = $subsByClinic[$cid] ?? collect();
            $n = rand(2, 4);
            for ($j = 0; $j < $n; $j++) {
                $fn = $this->pick($first);
                DB::table('doctors')->insert([
                    'clinic_id'        => $cid,
                    'sub_clinic_id'    => $subs->isNotEmpty() ? $subs->random()->id : null,
                    'name'             => $fn . ' ' . $this->pick($last),
                    'specialty'        => $this->pick($specialties),
                    'gender'           => in_array($fn, $female, true) ? 'female' : 'male',
                    'photo'            => null,
                    'bio'              => 'خبرة واسعة في تشخيص وعلاج الحالات بأحدث التقنيات الطبية.',
                    'qualifications'   => 'بورد سعودي، زمالة في التخصص الدقيق.',
                    'years_experience' => rand(3, 25),
                    'university'       => $this->pick($universities),
                    'languages'        => $this->pick($langs),
                    'is_active'        => true,
                    'sort_order'       => $j,
                    'created_at'       => $this->now,
                    'updated_at'       => $this->now,
                ]);
            }
        }
    }

    /** Fill the new doctor-detail columns for existing demo doctors (idempotent). */
    private function backfillDoctorDetails(): void
    {
        if (DB::table('doctors')->whereNotNull('gender')->exists()) {
            return;
        }

        foreach (['سارة', 'نورة', 'ريم', 'منى'] as $fn) {
            DB::table('doctors')->where('name', 'like', '%' . $fn . '%')->update(['gender' => 'female']);
        }
        DB::table('doctors')->whereNull('gender')->update(['gender' => 'male']);

        DB::table('doctors')->update([
            'qualifications' => 'بورد سعودي، زمالة في التخصص الدقيق.',
            'university'     => 'جامعة الملك سعود',
            'languages'     => 'العربية، الإنجليزية',
        ]);

        // Link each doctor to a department of its own complex.
        $subsByClinic = DB::table('sub_clinics')->get(['id', 'clinic_id'])->groupBy('clinic_id');
        DB::table('doctors')->orderBy('id')->select('id', 'clinic_id')->each(function ($d) use ($subsByClinic) {
            $subs = $subsByClinic[$d->clinic_id] ?? collect();
            if ($subs->isNotEmpty()) {
                DB::table('doctors')->where('id', $d->id)->update(['sub_clinic_id' => $subs->random()->id]);
            }
        });
    }

    private function seedPackages(): void
    {
        if (DB::table('packages')->count() >= 12) return;
        $names = ['باقة العناية المتكاملة', 'باقة الابتسامة', 'باقة الفحص الشامل', 'باقة التجميل', 'باقة الأمومة'];
        $svcByClinic = DB::table('services')->get(['id', 'clinic_id'])->groupBy('clinic_id');

        foreach ($this->ids('clinics') as $cid) {
            if (DB::table('packages')->where('clinic_id', $cid)->exists()) continue;
            $svc = $svcByClinic[$cid] ?? collect();
            if ($svc->count() < 2) continue;

            $price = rand(300, 1500);
            $packageId = DB::table('packages')->insertGetId([
                'clinic_id'   => $cid,
                'name'        => $this->pick($names),
                'description' => 'باقة مميزة تجمع عدة خدمات بسعر موفّر.',
                'price'       => $price,
                'old_price'   => $price + rand(100, 600),
                'expires_at'  => $this->now->copy()->addDays(rand(10, 60))->toDateString(),
                'is_active'   => true,
                'sort_order'  => 0,
                'created_at'  => $this->now,
                'updated_at'  => $this->now,
            ]);

            foreach ($svc->take(rand(2, min(3, $svc->count()))) as $s) {
                DB::table('package_service')->insert([
                    'package_id' => $packageId,
                    'service_id' => $s->id,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]);
            }
        }
    }

    private function seedArticles(): void
    {
        $target = 25;
        $existing = DB::table('articles')->count();
        $clinicIds = $this->ids('clinics');
        $titles = ['نصائح للعناية بالأسنان', 'كيف تحافظ على بشرتك', 'أهمية الفحص الدوري للعيون', 'التغذية السليمة للأطفال', 'الوقاية من أمراض القلب', 'متى تزور طبيب العظام', 'علامات تستوجب زيارة الطبيب', 'فوائد العلاج الطبيعي'];
        for ($i = $existing; $i < $target; $i++) {
            DB::table('articles')->insert([
                'clinic_id'        => $this->pick($clinicIds),
                'title'            => $this->pick($titles) . ' (' . ($i + 1) . ')',
                'slug'             => Str::slug('article-' . $i . '-' . Str::random(5)),
                'body'             => '<p>محتوى المقال الطبي التوعوي. يقدم معلومات مفيدة للقارئ حول الموضوع بأسلوب مبسط وواضح.</p>',
                'meta_description' => 'ملخص قصير للمقال الطبي التوعوي.',
                'tags'             => json_encode(['صحة', 'توعية']),
                'is_published'     => rand(0, 1) === 1,
                'published_at'     => $this->ts(rand(1, 90)),
                'views_count'      => rand(0, 5000),
                'ai_generated'     => rand(0, 1) === 1,
                'created_at'       => $this->ts(rand(1, 120)),
                'updated_at'       => $this->now,
            ]);
        }
    }

    private function seedBookings(): void
    {
        $target = 40;
        $existing = DB::table('bookings')->count();
        $clinicIds = $this->ids('clinics');
        $userIds = $this->ids('users');
        $statuses = ['new', 'contacted', 'appointment_set', 'completed', 'no_show', 'cancelled'];
        $names = ['عبدالله', 'سارة', 'محمد', 'منى', 'فيصل', 'جواهر', 'بدر', 'العنود'];
        for ($i = $existing; $i < $target; $i++) {
            $cid = $this->pick($clinicIds);
            $svc = DB::table('services')->where('clinic_id', $cid)->inRandomOrder()->value('id');
            DB::table('bookings')->insert([
                'clinic_id'      => $cid,
                'user_id'        => rand(0, 1) ? $this->pick($userIds) : null,
                'service_id'     => $svc,
                'customer_name'  => $this->pick($names) . ' ' . ($i + 1),
                'customer_phone' => $this->phone(400000 + $i),
                'notes'          => rand(0, 1) ? 'أرغب بموعد صباحي.' : null,
                'status'         => $this->pick($statuses),
                'clinic_notes'   => null,
                'appointment_at' => rand(0, 1) ? $this->now->copy()->addDays(rand(1, 20))->toDateTimeString() : null,
                'source'         => 'website',
                'created_at'     => $this->ts(rand(0, 60)),
                'updated_at'     => $this->now,
            ]);
        }
    }

    private function seedPriceQuotes(): void
    {
        $target = 25;
        $existing = DB::table('price_quote_requests')->count();
        $clinicIds = $this->ids('clinics');
        $userIds = $this->ids('users');
        $services = ['زراعة أسنان', 'عملية ليزك', 'جلسات تنحيف', 'تقويم شفاف', 'عملية تجميل'];
        $statuses = ['new', 'replied', 'closed'];
        for ($i = $existing; $i < $target; $i++) {
            $st = $this->pick($statuses);
            DB::table('price_quote_requests')->insert([
                'clinic_id'      => $this->pick($clinicIds),
                'user_id'        => rand(0, 1) ? $this->pick($userIds) : null,
                'customer_name'  => 'مستفسر ' . ($i + 1),
                'customer_phone' => $this->phone(500000 + $i),
                'service_name'   => $this->pick($services),
                'description'    => 'أرجو تزويدي بالسعر التقريبي والمدة.',
                'status'         => $st,
                'clinic_reply'   => $st === 'new' ? null : 'السعر يبدأ من ' . rand(500, 5000) . ' ريال.',
                'created_at'     => $this->ts(rand(0, 45)),
                'updated_at'     => $this->now,
            ]);
        }

        $this->seedBroadcastQuotes();
    }

    /** Broadcast quote requests (no clinic) targeting cities, with public/private replies. */
    private function seedBroadcastQuotes(): void
    {
        if (DB::table('price_quote_requests')->whereNull('clinic_id')->count() >= 10) {
            return;
        }

        $cityIds = collect($this->ids('cities'));
        $userIds = $this->ids('users');
        $services = ['زراعة أسنان', 'عملية ليزك', 'جلسات تنحيف', 'تقويم شفاف', 'باقة عناية شاملة'];
        $clinicsByCity = DB::table('clinics')->where('status', 'active')->get(['id', 'city_id'])->groupBy('city_id');

        for ($i = 0; $i < 10; $i++) {
            $cities = $cityIds->shuffle()->take(rand(1, 2))->values();

            $reqId = DB::table('price_quote_requests')->insertGetId([
                'clinic_id'      => null,
                'user_id'        => $this->pick($userIds),
                'customer_name'  => 'طالب عرض ' . ($i + 1),
                'customer_phone' => $this->phone(600000 + $i),
                'service_name'   => $this->pick($services),
                'description'    => 'أرغب بمعرفة السعر التقريبي والمدة وأقرب موعد متاح، مع تفاصيل الباقة إن وُجدت.',
                'status'         => 'replied',
                'created_at'     => $this->ts(rand(0, 20)),
                'updated_at'     => $this->now,
            ]);

            foreach ($cities as $cid) {
                DB::table('price_quote_request_city')->insertOrIgnore([
                    'price_quote_request_id' => $reqId,
                    'city_id'                => $cid,
                ]);
            }

            $clinics = collect();
            foreach ($cities as $cid) {
                $clinics = $clinics->merge($clinicsByCity[$cid] ?? collect());
            }

            foreach ($clinics->take(rand(1, 3))->values() as $idx => $clinic) {
                DB::table('price_quote_replies')->insertOrIgnore([
                    'price_quote_request_id' => $reqId,
                    'clinic_id'              => $clinic->id,
                    'body'                   => 'يسعدنا خدمتك. السعر يبدأ من ' . rand(500, 5000) . ' ريال حسب الحالة، ويمكن تحديد موعد خلال 48 ساعة.',
                    'price'                  => rand(500, 5000),
                    'is_public'              => $idx === 0, // first reply is public (feeds the board)
                    'created_at'             => $this->now,
                    'updated_at'             => $this->now,
                ]);
            }
        }
    }

    private function seedGoogleReviews(): void
    {
        $target = 40;
        $existing = DB::table('google_reviews')->count();
        $clinicIds = $this->ids('clinics');
        $names = ['زائر Google', 'مريض راضٍ', 'عميل', 'مستخدم خرائط'];
        $texts = ['خدمة ممتازة وطاقم محترف.', 'تجربة جيدة بشكل عام.', 'مواعيد منضبطة ونظافة عالية.', 'أنصح بالتعامل معهم.', 'الأسعار مناسبة والجودة عالية.'];
        for ($i = $existing; $i < $target; $i++) {
            DB::table('google_reviews')->insert([
                'clinic_id'        => $this->pick($clinicIds),
                'reviewer_name'    => $this->pick($names) . ' ' . ($i + 1),
                'rating'           => rand(3, 5),
                'review_text'      => $this->pick($texts),
                'google_review_id' => 'gr_' . Str::random(12),
                'reviewed_at'      => $this->ts(rand(1, 200)),
                'is_visible'       => true,
                'created_at'       => $this->now,
                'updated_at'       => $this->now,
            ]);
        }
    }

    private function seedSubscriptions(): void
    {
        $target = 25;
        $existing = DB::table('subscriptions')->count();
        $clinicIds = $this->ids('clinics');
        $adminIds = $this->ids('admins');
        $statuses = ['active', 'expired', 'cancelled', 'pending_payment'];
        for ($i = $existing; $i < $target; $i++) {
            $type = $this->pick(['basic', 'premium']);
            $start = $this->now->copy()->subDays(rand(10, 120));
            DB::table('subscriptions')->insert([
                'clinic_id'           => $this->pick($clinicIds),
                'type'                => $type,
                'amount'              => $type === 'premium' ? 400 : 300,
                'starts_at'           => $start->toDateTimeString(),
                'ends_at'             => $start->copy()->addDays(90)->toDateTimeString(),
                'status'              => $this->pick($statuses),
                'created_by_admin_id' => $this->pick($adminIds),
                'notes'               => null,
                'created_at'          => $start->toDateTimeString(),
                'updated_at'          => $this->now,
            ]);
        }
    }

    private function seedClinicStats(): void
    {
        $clinicIds = $this->ids('clinics');
        foreach ($clinicIds as $cid) {
            if (DB::table('clinic_stats')->where('clinic_id', $cid)->count() >= 5) continue;
            for ($d = 0; $d < 5; $d++) {
                DB::table('clinic_stats')->insertOrIgnore([
                    'clinic_id'            => $cid,
                    'date'                 => $this->now->copy()->subDays($d)->toDateString(),
                    'search_appearances'   => rand(10, 300),
                    'page_views'           => rand(5, 200),
                    'bookings_count'       => rand(0, 15),
                    'quote_requests_count' => rand(0, 8),
                    'created_at'           => $this->now,
                    'updated_at'           => $this->now,
                ]);
            }
        }
    }

    private function seedAiConversations(): void
    {
        $target = 20;
        $existing = DB::table('ai_conversations')->count();
        $clinicIds = $this->ids('clinics');
        $types = ['chat', 'article_generation', 'excel_analysis'];
        for ($i = $existing; $i < $target; $i++) {
            DB::table('ai_conversations')->insert([
                'clinic_id' => $this->pick($clinicIds),
                'title'     => 'محادثة مساعد ' . ($i + 1),
                'type'      => $this->pick($types),
                'messages'  => json_encode([
                    ['role' => 'user', 'content' => 'كيف أكتب مقالاً عن صحة الأسنان؟'],
                    ['role' => 'assistant', 'content' => 'يمكنك البدء بمقدمة بسيطة ثم نصائح عملية...'],
                ], JSON_UNESCAPED_UNICODE),
                'metadata'  => null,
                'created_at' => $this->ts(rand(1, 60)),
                'updated_at' => $this->now,
            ]);
        }
    }

    private function seedOtpCodes(): void
    {
        $target = 20;
        $existing = DB::table('otp_codes')->count();
        for ($i = $existing; $i < $target; $i++) {
            DB::table('otp_codes')->insert([
                'phone'      => $this->phone(600000 + $i),
                'code'       => str_pad((string) rand(0, 999999), 6, '0', STR_PAD_LEFT),
                'purpose'    => 'login',
                'is_used'    => rand(0, 1) === 1,
                'expires_at' => $this->now->copy()->addMinutes(5)->toDateTimeString(),
                'created_at' => $this->ts(rand(0, 10)),
                'updated_at' => $this->now,
            ]);
        }
    }

    private function seedSalesLeads(): void
    {
        $target = 25;
        $existing = DB::table('sales_leads')->count();
        $cityIds = $this->ids('cities');
        $adminIds = $this->ids('admins');
        $statuses = ['new', 'contacted', 'interested', 'negotiating', 'converted', 'lost'];
        for ($i = $existing; $i < $target; $i++) {
            DB::table('sales_leads')->insert([
                'clinic_name'       => 'مجمع محتمل ' . ($i + 1),
                'contact_name'      => 'مسؤول ' . ($i + 1),
                'phone'             => $this->phone(700000 + $i),
                'email'             => 'lead' . ($i + 1) . '@prospect.sa',
                'license_number'    => 'LIC-' . rand(10000, 99999),
                'city_id'           => $this->pick($cityIds),
                'district'          => 'حي ' . $this->pick(['الورود', 'النزهة', 'الياسمين', 'الربيع']),
                'address'           => 'شارع رقم ' . rand(1, 90),
                'status'            => $this->pick($statuses),
                'notes'             => 'تم التواصل المبدئي.',
                'sales_notes'       => 'مهتم بالباقة المميزة.',
                'assigned_to'       => $this->pick($adminIds),
                'next_follow_up_at' => $this->now->copy()->addDays(rand(-5, 15))->toDateTimeString(),
                'last_contact_at'   => $this->ts(rand(1, 30)),
                'created_at'        => $this->ts(rand(5, 120)),
                'updated_at'        => $this->now,
            ]);
        }
    }

    private function seedComplaints(): void
    {
        $target = 25;
        $existing = DB::table('complaints')->count();
        $clinicIds = $this->ids('clinics');
        $userIds = $this->ids('users');
        $adminIds = $this->ids('admins');
        $types = ['quality', 'pricing', 'misleading_info', 'other'];
        $statuses = ['new', 'in_review', 'resolved', 'rejected'];
        $priorities = ['low', 'medium', 'high'];
        $sources = ['customer', 'customer', 'clinic', 'admin'];
        for ($i = $existing; $i < $target; $i++) {
            $st = $this->pick($statuses);
            $source = $this->pick($sources);
            DB::table('complaints')->insert([
                'reference_code'    => 'CMP-' . strtoupper(Str::random(8)),
                'clinic_id'         => $this->pick($clinicIds),
                'user_id'           => $source === 'customer' ? $this->pick($userIds) : null,
                'booking_id'        => null,
                'source'            => $source,
                'customer_name'     => $source === 'clinic' ? ('مجمع ' . ($i + 1)) : ('مشتكٍ ' . ($i + 1)),
                'customer_phone'    => $this->phone(800000 + $i),
                'customer_email'    => rand(0, 1) ? 'c' . $i . '@mail.sa' : null,
                'type'              => $this->pick($types),
                'status'            => $st,
                'priority'          => $this->pick($priorities),
                'subject'           => 'شكوى بخصوص الخدمة',
                'description'       => 'تفاصيل الشكوى المقدمة من العميل حول التجربة.',
                'admin_notes'       => in_array($st, ['in_review', 'resolved', 'rejected']) ? 'تمت المراجعة.' : null,
                'resolution'        => $st === 'resolved' ? 'تم حل المشكلة والتواصل مع العميل.' : null,
                'assigned_admin_id' => $st === 'new' ? null : $this->pick($adminIds),
                'resolved_at'       => $st === 'resolved' ? $this->ts(rand(1, 10)) : null,
                'clinic_notified'   => rand(0, 1) === 1,
                'created_at'        => $this->ts(rand(0, 50)),
                'updated_at'        => $this->now,
            ]);
        }

        // Give the demo complaints a realistic source mix so the admin panel
        // shows customer + complex + admin complaints (idempotent — runs once).
        if (DB::table('complaints')->whereIn('source', ['customer', 'clinic'])->doesntExist()) {
            $ids = DB::table('complaints')->pluck('id')->shuffle();
            DB::table('complaints')->whereIn('id', $ids->take(8)->all())
                ->update(['source' => 'clinic', 'user_id' => null]);
            DB::table('complaints')->whereIn('id', $ids->slice(8, 10)->all())
                ->update(['source' => 'customer', 'user_id' => $this->pick($userIds)]);
        }
    }

    private function seedFavorites(): void
    {
        $target = 30;
        $existing = DB::table('favorites')->count();
        $userIds = $this->ids('users');
        $clinicIds = $this->ids('clinics');
        $made = $existing;
        $guard = 0;
        while ($made < $target && $guard < 500) {
            $guard++;
            $uid = $this->pick($userIds);
            $cid = $this->pick($clinicIds);
            $inserted = DB::table('favorites')->insertOrIgnore([
                'user_id' => $uid, 'clinic_id' => $cid,
                'created_at' => $this->ts(rand(1, 60)), 'updated_at' => $this->now,
            ]);
            $made += $inserted;
        }
    }

    private function seedAuditLogs(): void
    {
        $target = 25;
        $existing = DB::table('audit_logs')->count();
        $admins = DB::table('admins')->get(['id', 'name']);
        $actions = ['clinic.approved', 'clinic.rejected', 'clinic.suspended', 'clinic.updated', 'sales_lead.converted', 'subscription.created', 'city.updated'];
        for ($i = $existing; $i < $target; $i++) {
            $admin = $admins->random();
            DB::table('audit_logs')->insert([
                'admin_id'   => $admin->id,
                'admin_name' => $admin->name,
                'action'     => $this->pick($actions),
                'model_type' => 'App\\Models\\Clinic',
                'model_id'   => rand(1, 20),
                'old_values' => json_encode(['status' => 'pending']),
                'new_values' => json_encode(['status' => 'active']),
                'ip_address' => '127.0.0.' . rand(1, 254),
                'user_agent' => 'Mozilla/5.0 (DemoSeeder)',
                'created_at' => $this->ts(rand(0, 90)),
                'updated_at' => $this->now,
            ]);
        }
    }

    private function seedNotifications(): void
    {
        $target = 30;
        $existing = DB::table('platform_notifications')->count();
        $adminIds = $this->ids('admins');
        $clinicIds = $this->ids('clinics');
        $types = ['new_booking', 'new_complaint', 'new_price_quote', 'lead_converted', 'subscription_expiring'];
        $priorities = ['low', 'normal', 'high', 'urgent'];
        for ($i = $existing; $i < $target; $i++) {
            $toAdmin = rand(0, 1) === 1;
            DB::table('platform_notifications')->insert([
                'notifiable_type' => $toAdmin ? 'App\\Models\\Admin' : 'App\\Models\\Clinic',
                'notifiable_id'   => $toAdmin ? $this->pick($adminIds) : $this->pick($clinicIds),
                'type'            => $this->pick($types),
                'icon'            => 'heroicon-o-bell',
                'url'             => $toAdmin ? '/app/admin/dashboard' : '/app/clinic/dashboard',
                'priority'        => $this->pick($priorities),
                'title'           => 'إشعار تجريبي ' . ($i + 1),
                'body'            => 'هذا إشعار توضيحي تم إنشاؤه بواسطة DemoSeeder.',
                'data'            => json_encode(['demo' => true]),
                'read_at'         => rand(0, 1) ? $this->ts(rand(0, 5)) : null,
                'created_at'      => $this->ts(rand(0, 20)),
                'updated_at'      => $this->now,
            ]);
        }
    }
}
