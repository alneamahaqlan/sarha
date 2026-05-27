<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

/**
 * Assigns a service_category_id to every existing service that doesn't
 * have one, based on the service name. Idempotent — only touches rows
 * where service_category_id IS NULL, so re-running on an already-classified
 * table is a no-op.
 *
 * The keyword → category map below is built from the SERVICE_POOL in
 * MassiveCityCoverageSeeder. Anything not matched falls back to the
 * "general consultations" bucket so a service never stays orphaned.
 *
 * Run:  php artisan db:seed --class=BackfillServiceCategoriesSeeder --force
 */
class BackfillServiceCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        if (Service::whereNull('service_category_id')->doesntExist()) {
            $this->command?->info('BackfillServiceCategoriesSeeder: nothing to backfill — already classified.');
            return;
        }

        // Load lookup: slug => id, so we can reference categories by slug
        // and not break if an admin later renames one.
        $cats = ServiceCategory::pluck('id', 'slug');

        // Each rule: [matcher_callback, category_slug]. First match wins
        // (most specific patterns listed first).
        $rules = [
            // Laser variants — match BEFORE generic dermatology
            [fn ($n) => str_contains($n, 'ليزر') && str_contains($n, 'الشعر'), 'laser-hair-removal'],
            [fn ($n) => str_contains($n, 'ليزر فراكشنال') || str_contains($n, 'هوليوود فيشل') || str_contains($n, 'كاربون'),
                'skin-resurfacing-laser'],
            [fn ($n) => str_contains($n, 'ليزر') && str_contains($n, 'ندبات'), 'skin-resurfacing-laser'],
            [fn ($n) => str_starts_with($n, 'ليزر '),                          'skin-resurfacing-laser'],

            // Dentistry
            [fn ($n) => str_contains($n, 'تنظيف') && str_contains($n, 'الأسنان'), 'cleaning-polishing'],
            [fn ($n) => str_contains($n, 'تبييض'),                                 'teeth-whitening'],
            [fn ($n) => str_contains($n, 'حشوة'),                                  'dental-fillings'],
            [fn ($n) => str_contains($n, 'زراعة') && (str_contains($n, 'سن') || str_contains($n, 'فوري')),
                'dental-implants'],
            [fn ($n) => str_contains($n, 'تقويم'),                                 'orthodontics'],
            [fn ($n) => str_contains($n, 'علاج عصب') || str_contains($n, 'عصب'),  'root-canal'],
            [fn ($n) => str_contains($n, 'خلع'),                                   'extraction-oral-surgery'],
            [fn ($n) => str_contains($n, 'ابتسامة هوليوود') || str_contains($n, 'فينير') || str_contains($n, 'لومينير'),
                'hollywood-smile-veneers'],
            [fn ($n) => str_contains($n, 'تركيب طقم') || str_contains($n, 'طقم'), 'dental-prosthetics'],
            [fn ($n) => str_contains($n, 'كشف أسنان'),                             'dental-prosthetics'],

            // Dermatology / cosmetic / laser fallback
            [fn ($n) => str_contains($n, 'حقن بوتكس') || str_contains($n, 'بوتكس'),  'botox-filler-injections'],
            [fn ($n) => str_contains($n, 'فيلر'),                                     'botox-filler-injections'],
            [fn ($n) => str_contains($n, 'ميزوثيرابي'),                              'mesotherapy'],
            [fn ($n) => str_contains($n, 'تقشير'),                                    'peeling-brightening'],
            [fn ($n) => str_contains($n, 'نضارة') || str_contains($n, 'تفتيح'),       'peeling-brightening'],
            [fn ($n) => str_contains($n, 'نحت') || str_contains($n, 'كولتك') || str_contains($n, 'شد ال'),
                'body-contouring'],
            [fn ($n) => str_contains($n, 'تنظيف بشرة') || str_contains($n, 'تنظيف عميق'),
                'facials-skincare'],
            [fn ($n) => str_contains($n, 'كشف جلدية') || str_contains($n, 'حب الشباب'),
                'dermatology-consults'],

            // Ophthalmology
            [fn ($n) => str_contains($n, 'ليزك') || str_contains($n, 'فيمتو'),    'vision-correction-lasik'],
            [fn ($n) => str_contains($n, 'فحص نظر') || str_contains($n, 'كشف عيون') || str_contains($n, 'فحص قاع') || str_contains($n, 'عدسات لاصقة'),
                'eye-exams'],
            [fn ($n) => str_contains($n, 'المياه البيضاء') || str_contains($n, 'جفاف العين'),
                'eye-surgeries-diseases'],

            // Pediatrics
            [fn ($n) => str_contains($n, 'كشف أطفال') || str_contains($n, 'تطعيمات') || str_contains($n, 'حديثي الولادة') || str_contains($n, 'رضاعة') || str_contains($n, 'تقييم نمو'),
                'pediatrics-vaccinations'],

            // GYN / OBS
            [fn ($n) => str_contains($n, 'نساء') || str_contains($n, 'متابعة حمل') || str_contains($n, 'سونار رباعي') || str_contains($n, 'هرمونات') || str_contains($n, 'لولب') || str_contains($n, 'تنظيف رحم') || str_contains($n, 'عقم'),
                'gynecology-obstetrics'],

            // Ortho
            [fn ($n) => str_contains($n, 'كشف عظام') || str_contains($n, 'حقن مفصل') || str_contains($n, 'بلازما PRP') || str_contains($n, 'أشعة عظام') || str_contains($n, 'جبس') || str_contains($n, 'تجبير'),
                'orthopedics-joints'],

            // Cardio
            [fn ($n) => str_contains($n, 'تخطيط قلب') || str_contains($n, 'إيكو') || str_contains($n, 'كشف قلب') || str_contains($n, 'اختبار جهد') || str_contains($n, 'هولتر'),
                'cardiology'],

            // ENT
            [fn ($n) => str_contains($n, 'أنف') || str_contains($n, 'أذن') || str_contains($n, 'سمع') || str_contains($n, 'جلسة بخار'),
                'ent'],

            // Internal med
            [fn ($n) => str_contains($n, 'كشف باطنية') || str_contains($n, 'متابعة سكري') || str_contains($n, 'متابعة ضغط') || str_contains($n, 'فحص شامل'),
                'internal-medicine'],

            // Psych
            [fn ($n) => str_contains($n, 'نفسي'),                                  'mental-health'],

            // Nutrition
            [fn ($n) => str_contains($n, 'تغذية') || str_contains($n, 'تنحيف') || str_contains($n, 'اكتساب وزن') || str_contains($n, 'InBody'),
                'nutrition-weight-loss'],

            // Physical therapy
            [fn ($n) => str_contains($n, 'علاج طبيعي') || str_contains($n, 'كهرباء') || str_contains($n, 'تأهيل') || str_contains($n, 'تدليك علاجي'),
                'physical-therapy-rehab'],

            // Labs
            [fn ($n) => str_starts_with($n, 'تحليل ') || str_contains($n, 'HbA1c') || str_contains($n, 'TSH'),
                'lab-tests'],

            // Radiology
            [fn ($n) => str_contains($n, 'أشعة') || str_contains($n, 'رنين') || str_contains($n, 'CT') || str_contains($n, 'MRI') || str_contains($n, 'ماموجرام') || str_contains($n, 'سونار') || str_contains($n, 'دوبلر'),
                'radiology-imaging'],

            // Urology
            [fn ($n) => str_contains($n, 'مسالك') || str_contains($n, 'تفتيت حصوة') || str_contains($n, 'سائل منوي'),
                'urology'],

            // Surgery
            [fn ($n) => str_contains($n, 'جراحة عامة') || str_contains($n, 'كيس دهني') || str_contains($n, 'ختان'),
                'general-surgery'],

            // General catch-all
            [fn ($n) => str_contains($n, 'كشف عام'),                              'general-consultations'],
        ];

        // Fallback for unmatched services so we never leave NULLs behind.
        $fallbackId = $cats['general-consultations'] ?? $cats->first();

        $matched   = 0;
        $unmatched = 0;
        Service::whereNull('service_category_id')->chunkById(500, function ($services) use ($rules, $cats, $fallbackId, &$matched, &$unmatched) {
            foreach ($services as $svc) {
                $catId = null;
                foreach ($rules as [$test, $slug]) {
                    if ($test($svc->name)) {
                        $catId = $cats[$slug] ?? null;
                        if ($catId) break;
                    }
                }
                if (! $catId) {
                    $catId = $fallbackId;
                    $unmatched++;
                } else {
                    $matched++;
                }
                $svc->forceFill(['service_category_id' => $catId])->saveQuietly();
            }
        });

        $this->command?->info("BackfillServiceCategoriesSeeder: classified {$matched} services + {$unmatched} fell to fallback.");
    }
}
