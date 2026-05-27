<?php

namespace Database\Seeders;

use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the canonical service categories an admin starts the platform
 * with. ~30 categories grouped by specialty so every service in the
 * MassiveCityCoverageSeeder pool can be classified.
 *
 * Idempotent: each row uses updateOrCreate on the slug, so re-running
 * doesn't duplicate or wipe an admin's later edits.
 *
 * Run:  php artisan db:seed --class=ServiceCategoriesSeeder --force
 */
class ServiceCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // ───── DENTISTRY ─────
            ['تنظيف وتلميع', 'Cleaning & Polishing', '🦷'],
            ['تبييض الأسنان', 'Teeth Whitening', '✨'],
            ['حشوات الأسنان', 'Dental Fillings', '🦷'],
            ['زراعة الأسنان', 'Dental Implants', '🦷'],
            ['تقويم الأسنان', 'Orthodontics', '🦷'],
            ['علاج العصب', 'Root Canal', '🦷'],
            ['خلع وجراحة الفم', 'Extraction & Oral Surgery', '🦷'],
            ['ابتسامة هوليوود وفينير', 'Hollywood Smile & Veneers', '✨'],
            ['تركيبات الأسنان', 'Dental Prosthetics', '🦷'],
            // ───── DERMATOLOGY / COSMETIC / LASER ─────
            ['كشف وعلاج الجلدية', 'Dermatology Consults', '🩺'],
            ['ليزر إزالة الشعر', 'Laser Hair Removal', '✨'],
            ['ليزر تجديد البشرة', 'Skin Resurfacing Laser', '✨'],
            ['تقشير وتفتيح البشرة', 'Peeling & Brightening', '✨'],
            ['الحقن التجميلية (بوتكس / فيلر)', 'Botox & Filler Injections', '💉'],
            ['الميزوثيرابي', 'Mesotherapy', '💉'],
            ['نحت وشد الجسم', 'Body Contouring', '✨'],
            ['تنظيف وعناية بالبشرة', 'Facials & Skincare', '✨'],
            // ───── OPHTHALMOLOGY ─────
            ['كشف وفحص النظر', 'Eye Exams', '👁'],
            ['عمليات تصحيح النظر (ليزك)', 'Vision Correction (LASIK)', '👁'],
            ['عمليات وأمراض العيون', 'Eye Surgeries & Diseases', '👁'],
            // ───── PEDIATRICS ─────
            ['طب الأطفال والتطعيمات', 'Pediatrics & Vaccinations', '👶'],
            // ───── GYN / OBS ─────
            ['نساء وولادة وحمل', 'Gynecology & Obstetrics', '🤰'],
            // ───── ORTHO + PHYSIO ─────
            ['عظام ومفاصل', 'Orthopedics & Joints', '🦴'],
            ['علاج طبيعي وتأهيل', 'Physical Therapy & Rehab', '💪'],
            // ───── CARDIO + INTERNAL + ENT ─────
            ['القلب والأوعية', 'Cardiology', '❤️'],
            ['باطنية وأمراض مزمنة', 'Internal Medicine', '🩺'],
            ['أنف وأذن وحنجرة', 'ENT', '👂'],
            // ───── PSYCH + NUTRITION ─────
            ['الصحة النفسية', 'Mental Health', '🧠'],
            ['التغذية والتنحيف', 'Nutrition & Weight Loss', '🥗'],
            // ───── LABS + RADIOLOGY ─────
            ['التحاليل المخبرية', 'Lab Tests', '🧪'],
            ['الأشعة والتصوير', 'Radiology & Imaging', '🩻'],
            // ───── UROLOGY + SURGERY + GENERAL ─────
            ['المسالك البولية', 'Urology', '🩺'],
            ['الجراحة العامة', 'General Surgery', '🔪'],
            ['الكشف العام والاستشارات', 'General Consultations', '🩺'],
            // ───── PACKAGES / OFFERS catch-all ─────
            ['باقات وعروض', 'Packages & Offers', '🎁'],
        ];

        foreach ($categories as $index => [$name, $nameEn, $emoji]) {
            ServiceCategory::updateOrCreate(
                ['slug' => Str::slug($nameEn)],
                [
                    'name'       => $name,
                    'name_en'    => $nameEn,
                    'emoji'      => $emoji,
                    'is_active'  => true,
                    'sort_order' => $index,
                ],
            );
        }

        $this->command?->info('ServiceCategoriesSeeder: ' . count($categories) . ' categories ready.');
    }
}
