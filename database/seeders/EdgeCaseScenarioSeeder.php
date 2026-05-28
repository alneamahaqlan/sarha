<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Adds the edge-case and gap-filling data that MassiveCityCoverageSeeder and
 * YearOfUsageSeeder don't cover, so QA can exercise the full surface area.
 *
 * Fills:
 *   - before_after_photos (currently empty) — ~2,500 rows spread across
 *     clinics with a sub_clinic/service mix.
 *   - category_requests (currently empty) — ~120 rows in every status
 *     (pending / approved / rejected), some linked to a category, some not.
 *
 * Edge-case scenarios written into existing rows:
 *   - Deep discount offers (50–80% off) on ~80 services so the discount
 *     badge renders at its full range.
 *   - A handful of bookings far in the future (next 60 days) so the
 *     "upcoming appointments" UI has data.
 *
 * Additive & idempotent: every section skips work if its target is already
 * met, so running this multiple times is safe.
 */
class EdgeCaseScenarioSeeder extends Seeder
{
    private Carbon $now;

    public function run(): void
    {
        $this->now = now();

        if (DB::table('clinics')->count() === 0 || DB::table('services')->count() === 0) {
            $this->command?->warn('EdgeCaseScenarioSeeder: needs clinics + services (run MassiveCityCoverageSeeder first).');
            return;
        }

        $this->seedBeforeAfterPhotos(2500);
        $this->seedCategoryRequests(120);
        $this->seedDeepDiscountOffers(80);
        $this->seedFutureBookings(150);

        $this->command?->info('EdgeCaseScenarioSeeder: gap data + edge cases in place.');
    }

    /**
     * before_after_photos — clinics in cosmetic / dermatology / dental /
     * ophthalmology categories get the bulk (matches real-world usage where
     * those are the procedures showcased).
     */
    private function seedBeforeAfterPhotos(int $target): void
    {
        $existing = DB::table('before_after_photos')->count();
        if ($existing >= $target) {
            return;
        }

        $showcaseSlugs = ['dentistry', 'dermatology', 'cosmetics', 'ophthalmology', 'orthopedics'];
        $catIds = DB::table('categories')->whereIn('slug', $showcaseSlugs)->pluck('id')->all();

        // Clinics that already have a service in one of the showcase categories.
        $clinicIds = DB::table('services')
            ->whereIn('category_id', $catIds)
            ->distinct()
            ->pluck('clinic_id')
            ->all();

        if (empty($clinicIds)) {
            return;
        }

        // Pre-resolve a small pool of services per clinic so the photo can
        // optionally link to one.
        $servicesByClinic = DB::table('services')
            ->whereIn('clinic_id', $clinicIds)
            ->whereIn('category_id', $catIds)
            ->get(['id', 'clinic_id', 'sub_clinic_id'])
            ->groupBy('clinic_id');

        $titles = [
            'تحويل ابتسامة - حالة كاملة', 'نتيجة تبييض زوم', 'فينير سيراميك',
            'حقن فيلر شفايف', 'حقن بوتكس جبهة', 'إزالة ندبات بالليزر',
            'علاج هالات سوداء', 'شد الوجه بالخيوط', 'تنحيف الذقن المزدوج',
            'علاج حب الشباب', 'تقشير كيميائي', 'فراكشنال CO2',
            'تقويم انفزلاين', 'زراعة الأسنان', 'هوليوود سمايل',
            'تصحيح النظر بالليزك', 'علاج شعر التساقط', 'تنسيق قوام',
            null, null,
        ];

        $rows = [];
        for ($i = $existing; $i < $target; $i++) {
            $clinicId = $clinicIds[array_rand($clinicIds)];
            $clinicServices = $servicesByClinic[$clinicId] ?? collect();
            $svc = $clinicServices->isNotEmpty() && rand(0, 1) ? $clinicServices->random() : null;
            $rows[] = [
                'clinic_id'     => $clinicId,
                'sub_clinic_id' => $svc?->sub_clinic_id,
                'service_id'    => $svc?->id,
                'title'         => $titles[array_rand($titles)],
                // Placeholder URLs — the model just stores paths, no validation.
                'before_image'  => 'demo/before-after/before-' . (($i % 25) + 1) . '.jpg',
                'after_image'   => 'demo/before-after/after-' . (($i % 25) + 1) . '.jpg',
                'is_active'     => rand(0, 15) > 0, // ~6% hidden
                'sort_order'    => $i % 100,
                'created_at'    => $this->now->copy()->subDays(rand(1, 280))->toDateTimeString(),
                'updated_at'    => $this->now,
            ];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('before_after_photos')->insert($chunk);
        }
    }

    /**
     * category_requests — every clinic-driven specialty request flow:
     * pending awaiting admin, approved (linked to a category), rejected.
     */
    private function seedCategoryRequests(int $target): void
    {
        $existing = DB::table('category_requests')->count();
        if ($existing >= $target) {
            return;
        }

        $clinicIds = DB::table('clinics')->pluck('id')->all();
        $adminIds  = DB::table('admins')->pluck('id')->all();
        $catIds    = DB::table('categories')->pluck('id')->all();
        if (empty($clinicIds) || empty($adminIds) || empty($catIds)) {
            return;
        }

        $proposed = [
            'طب الأسنان التجميلي المتقدم', 'علاج الإدمان وإعادة التأهيل',
            'طب الأعشاب التكميلي', 'علاج العقم والأنابيب',
            'جراحة السمنة', 'علاج اضطرابات النوم', 'طب الأسرة الوقائي',
            'علاج طبيعي للأطفال', 'العلاج بالخلايا الجذعية',
            'الطب الرياضي', 'علاج آلام مزمنة', 'علاج تشوهات النطق',
            'طب الأقدام', 'علاج اضطرابات التوحد', 'طب الكلى المتنقل',
            'علاج طبيعي ما بعد الجلطة', 'العلاج المعرفي السلوكي',
        ];

        $rows = [];
        for ($i = $existing; $i < $target; $i++) {
            // Older requests are decided; newer ones still pending.
            $d = rand(0, 180);
            if ($d > 30) {
                $status = (array_rand([0 => 0, 1 => 1, 2 => 2]) === 2) ? 'rejected' : 'approved';
            } else {
                $status = rand(0, 2) === 0 ? 'approved' : 'pending';
            }
            $rows[] = [
                'clinic_id'   => $clinicIds[array_rand($clinicIds)],
                'name'        => $proposed[array_rand($proposed)],
                'status'      => $status,
                'reviewed_by' => $status === 'pending' ? null : $adminIds[array_rand($adminIds)],
                'category_id' => $status === 'approved' ? $catIds[array_rand($catIds)] : null,
                'created_at'  => $this->now->copy()->subDays($d)->toDateTimeString(),
                'updated_at'  => $status === 'pending'
                    ? $this->now->copy()->subDays($d)->toDateTimeString()
                    : $this->now->copy()->subDays(max(0, $d - rand(1, 7)))->toDateTimeString(),
            ];
        }
        DB::table('category_requests')->insert($rows);
    }

    /**
     * Deep discounts — picks services that currently have NO active offer and
     * stamps them with a 50–80% discount + 30–90 day expiry. Drives the
     * "huge discount badge" rendering path.
     */
    private function seedDeepDiscountOffers(int $count): void
    {
        $candidates = DB::table('services')
            ->whereNull('old_price')
            ->where('is_active', true)
            ->inRandomOrder()
            ->limit($count)
            ->get(['id', 'price']);

        foreach ($candidates as $svc) {
            $discountPct = rand(50, 80);
            // old_price = price / (1 - discount/100)
            $oldPrice = (int) round($svc->price / (1 - $discountPct / 100));
            DB::table('services')->where('id', $svc->id)->update([
                'old_price'         => $oldPrice,
                'offer_expires_at'  => $this->now->copy()->addDays(rand(30, 90))->toDateTimeString(),
                'is_featured_offer' => true,
                'updated_at'        => $this->now,
            ]);
        }
    }

    /**
     * Future bookings — pushes a small batch of "appointment_set" rows into
     * the next 60 days so the dashboard's upcoming-appointments widgets and
     * the clinic-side calendar always have something to render.
     */
    private function seedFutureBookings(int $count): void
    {
        $existingFuture = DB::table('bookings')
            ->where('appointment_at', '>', $this->now)
            ->where('status', 'appointment_set')
            ->count();
        if ($existingFuture >= $count) {
            return;
        }

        $services = DB::table('services')->where('is_active', true)
            ->inRandomOrder()->limit($count * 3)
            ->get(['id', 'clinic_id']);
        $userIds = DB::table('users')->pluck('id')->all();
        $names = ['عبدالله', 'سارة', 'محمد', 'منى', 'فيصل', 'جواهر', 'بدر', 'العنود', 'ناصر', 'ريم'];

        $rows = [];
        $needed = $count - $existingFuture;
        foreach ($services->take($needed) as $i => $svc) {
            $appointment = $this->now->copy()->addDays(rand(1, 60))->setTime(rand(9, 20), [0, 30][rand(0, 1)]);
            $rows[] = [
                'reference_code' => 'EDG-' . strtoupper(Str::random(8)),
                'clinic_id'      => $svc->clinic_id,
                'user_id'        => $userIds && rand(0, 1) ? $userIds[array_rand($userIds)] : null,
                'service_id'     => $svc->id,
                'customer_name'  => $names[array_rand($names)],
                'customer_phone' => '05' . str_pad((string) (70000000 + $i), 8, '0', STR_PAD_LEFT),
                'notes'          => rand(0, 2) === 0 ? 'الرجاء التواصل قبل الموعد بساعة.' : null,
                'status'         => 'appointment_set',
                'clinic_notes'   => null,
                'appointment_at' => $appointment->toDateTimeString(),
                'source'         => ['website', 'ai_assistant', 'phone'][rand(0, 2)],
                'created_at'     => $this->now->copy()->subDays(rand(0, 14))->toDateTimeString(),
                'updated_at'     => $this->now,
            ];
        }
        if ($rows) {
            DB::table('bookings')->insert($rows);
        }
    }
}
