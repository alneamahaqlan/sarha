<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Category;
use App\Models\City;
use App\Models\Clinic;
use App\Models\LandingPage;
use App\Models\Offer;
use App\Services\LandingPageBuilderService;
use Illuminate\Database\Seeder;

/**
 * Seeds 10 varied demo landing pages — one (or two) of every type
 * (clinic / offer / city / category / comparison / custom) and every
 * header/footer chrome style (default / minimal / custom / none / clinic).
 *
 * Idempotent: each page upserts on its slug and its default blocks are
 * seeded once. Wrapped by DemoBatch::wrap('landing_pages', …) in
 * DatabaseSeeder so every row is stamped is_demo=true / demo_batch='landing_pages'
 * and the Seeder Center can hide / restore / purge it without touching real
 * (app-created) landing pages.
 */
class LandingPageSeeder extends Seeder
{
    public function run(): void
    {
        $builder = app(LandingPageBuilderService::class);

        $adminId      = Admin::query()->value('id');
        $clinicBySlug = Clinic::pluck('id', 'slug');                 // slug => id
        $activeClinic = Clinic::where('status', 'active')->pluck('id')->all();
        $cityByName   = City::pluck('id', 'name');                    // name => id
        $catByEn      = Category::pluck('id', 'name_en');             // name_en => id
        $offers       = Offer::query()->inRandomOrder()->limit(4)->get(['id', 'clinic_id']);

        if (empty($activeClinic)) {
            $this->command?->warn('LandingPageSeeder: no active clinics — skipping.');
            return;
        }

        // Stable picks for the named demo clinics, with graceful fallbacks.
        $dental  = $clinicBySlug['riyadh-dental']        ?? $activeClinic[0];
        $eyes    = $clinicBySlug['riyadh-ophthalmology'] ?? $activeClinic[array_key_last($activeClinic)];
        $derma   = $clinicBySlug['jeddah-dermatology']   ?? $activeClinic[0];
        $compare = array_slice($activeClinic, 0, 3);

        $riyadh  = $cityByName['الرياض'] ?? $cityByName->first();
        $jeddah  = $cityByName['جدة']    ?? $cityByName->first();
        $dentCat = $catByEn['Dentistry']  ?? $catByEn->first();
        $dermCat = $catByEn['Dermatology'] ?? $catByEn->first();

        $offerA = $offers->get(0);
        $offerB = $offers->get(1);

        // Phone helper — reuse the linked clinic's phone for realism.
        $phone = fn ($clinicId) => Clinic::where('id', $clinicId)->value('phone') ?: '0555000001';

        $pages = [];

        // 1) CLINIC — showcases the new "clinic header" chrome (full profile hero).
        $pages[] = [
            'type' => 'clinic', 'status' => 'published',
            'slug' => 'demo-clinic-dental-riyadh',
            'title_ar' => 'مركز الرياض للأسنان — ابتسامتك تبدأ هنا',
            'title_en' => 'Riyadh Dental Center',
            'internal_name' => '[تجريبي] صفحة مجمع الأسنان (ترويسة المجمع)',
            'clinic_id' => $dental,
            'header_mode' => 'clinic', 'footer_mode' => 'default',
            'cta_style' => 'scroll_booking', 'cta_label_ar' => 'احجز موعدك', 'cta_label_en' => 'Book now',
            'whatsapp_phone' => $phone($dental), 'call_phone' => $phone($dental),
            'seo_title_ar' => 'مركز الرياض للأسنان', 'seo_description_ar' => 'خدمات تقويم وتجميل وزراعة الأسنان بأحدث التقنيات في الرياض.',
        ];

        // 2) OFFER — minimal header + minimal footer, with a countdown block.
        $pages[] = [
            'type' => 'offer', 'status' => 'published',
            'slug' => 'demo-offer-teeth-whitening',
            'title_ar' => 'عرض خاص: تبييض الأسنان بخصم ٤٠٪',
            'title_en' => 'Special Offer: Teeth Whitening 40% Off',
            'internal_name' => '[تجريبي] صفحة عرض (هيدر/فوتر مبسّط)',
            'offer_id' => $offerA?->id,
            'clinic_id' => $offerA?->clinic_id, // fallback context if offer relation thin
            'header_mode' => 'minimal', 'footer_mode' => 'minimal',
            'header_config' => ['sticky' => true],
            'footer_config' => ['copyright' => '© ' . date('Y') . ' دليل المجمعات الطبية — جميع الحقوق محفوظة'],
            'cta_style' => 'scroll_booking', 'cta_label_ar' => 'احجز العرض الآن',
            'ends_at' => now()->addDays(14),
            'whatsapp_phone' => $phone($offerA?->clinic_id ?? $dental),
        ];

        // 3) CITY — default platform chrome.
        $pages[] = [
            'type' => 'city', 'status' => 'published',
            'slug' => 'demo-city-riyadh-clinics',
            'title_ar' => 'أفضل المجمعات الطبية في الرياض',
            'title_en' => 'Top Medical Complexes in Riyadh',
            'internal_name' => '[تجريبي] صفحة مدينة (الرياض)',
            'city_id' => $riyadh,
            'header_mode' => 'default', 'footer_mode' => 'default',
            'cta_style' => 'scroll_booking', 'cta_label_ar' => 'اعرض المجمعات',
            'seo_title_ar' => 'مجمعات الرياض الطبية', 'seo_description_ar' => 'دليل أفضل المجمعات والعيادات الطبية في مدينة الرياض.',
        ];

        // 4) CATEGORY — fully CUSTOM header + footer (own colors, links, CTA, social).
        $pages[] = [
            'type' => 'category', 'status' => 'published',
            'slug' => 'demo-category-dentistry',
            'title_ar' => 'عيادات ومراكز الأسنان',
            'title_en' => 'Dental Clinics & Centers',
            'internal_name' => '[تجريبي] صفحة تخصص (هيدر/فوتر مخصّص)',
            'category_id' => $dentCat,
            'header_mode' => 'custom', 'footer_mode' => 'custom',
            'header_config' => [
                'show_language' => true, 'sticky' => true,
                'bg_color' => '#0f766e', 'text_color' => '#ffffff',
                'cta_label' => 'احجز الآن', 'cta_url' => '#booking',
                'links' => [
                    ['label' => 'الخدمات', 'url' => '#services', 'new_tab' => false],
                    ['label' => 'العروض',  'url' => '#offers',   'new_tab' => false],
                    ['label' => 'الأسئلة', 'url' => '#faq',      'new_tab' => false],
                ],
            ],
            'footer_config' => [
                'about' => 'نخبة عيادات ومراكز الأسنان في مكان واحد — قارن واحجز بثقة.',
                'copyright' => '© ' . date('Y') . ' دليل المجمعات الطبية',
                'phone' => '0550000000', 'email' => 'dental@example.sa', 'whatsapp' => '966550000000',
                'bg_color' => '#0f172a', 'text_color' => '#cbd5e1',
                'social' => ['instagram' => 'https://instagram.com/example', 'twitter' => 'https://x.com/example'],
                'links' => [
                    ['label' => 'سياسة الخصوصية', 'url' => '/privacy', 'new_tab' => false],
                    ['label' => 'تواصل معنا',     'url' => '/contact', 'new_tab' => false],
                ],
            ],
            'cta_style' => 'scroll_booking', 'cta_label_ar' => 'احجز موعداً',
        ];

        // 5) COMPARISON — minimal header + NO footer (focused comparison page).
        $pages[] = [
            'type' => 'comparison', 'status' => 'published',
            'slug' => 'demo-comparison-dental',
            'title_ar' => 'قارن بين أفضل ٣ مراكز للأسنان',
            'title_en' => 'Compare the Top 3 Dental Centers',
            'internal_name' => '[تجريبي] صفحة مقارنة (بلا فوتر)',
            'header_mode' => 'minimal', 'footer_mode' => 'none',
            'cta_style' => 'scroll_booking', 'cta_label_ar' => 'اختر الأنسب لك',
            '_compare' => $compare,
        ];

        // 6) CUSTOM — NO header (distraction-free) + minimal footer.
        $pages[] = [
            'type' => 'custom', 'status' => 'published',
            'slug' => 'demo-custom-summer-derma',
            'title_ar' => 'حملة العناية الصيفية بالبشرة',
            'title_en' => 'Summer Skin Care Campaign',
            'internal_name' => '[تجريبي] صفحة مخصّصة (بلا هيدر)',
            'clinic_id' => $derma,
            'header_mode' => 'none', 'footer_mode' => 'minimal',
            'footer_config' => ['copyright' => '© ' . date('Y') . ' حملة تجريبية'],
            'cta_style' => 'scroll_booking', 'cta_label_ar' => 'احجزي استشارتك',
            'whatsapp_phone' => $phone($derma),
        ];

        // 7) CLINIC #2 — clinic header chrome again, minimal footer.
        $pages[] = [
            'type' => 'clinic', 'status' => 'published',
            'slug' => 'demo-clinic-eyes-riyadh',
            'title_ar' => 'مركز البصر للعيون',
            'title_en' => 'Al-Basar Eye Center',
            'internal_name' => '[تجريبي] صفحة مجمع العيون (ترويسة المجمع)',
            'clinic_id' => $eyes,
            'header_mode' => 'clinic', 'footer_mode' => 'minimal',
            'footer_config' => ['copyright' => '© ' . date('Y') . ' مركز البصر'],
            'cta_style' => 'scroll_booking', 'cta_label_ar' => 'احجز فحصك',
            'whatsapp_phone' => $phone($eyes), 'call_phone' => $phone($eyes),
        ];

        // 8) OFFER #2 — custom header, default footer, DRAFT.
        $pages[] = [
            'type' => 'offer', 'status' => 'draft',
            'slug' => 'demo-offer-checkup-bundle',
            'title_ar' => 'باقة الفحص الشامل',
            'title_en' => 'Full Check-up Bundle',
            'internal_name' => '[تجريبي] صفحة عرض (مسودة)',
            'offer_id' => $offerB?->id,
            'clinic_id' => $offerB?->clinic_id,
            'header_mode' => 'custom', 'footer_mode' => 'default',
            'header_config' => [
                'sticky' => true, 'bg_color' => '#7c3aed', 'text_color' => '#ffffff',
                'cta_label' => 'اطلب الباقة', 'cta_url' => '#booking',
                'links' => [['label' => 'التفاصيل', 'url' => '#services', 'new_tab' => false]],
            ],
            'cta_style' => 'scroll_booking', 'cta_label_ar' => 'اطلب الباقة',
        ];

        // 9) CATEGORY #2 — Dermatology, default chrome.
        $pages[] = [
            'type' => 'category', 'status' => 'published',
            'slug' => 'demo-category-dermatology',
            'title_ar' => 'عيادات الجلدية والتجميل',
            'title_en' => 'Dermatology & Aesthetics Clinics',
            'internal_name' => '[تجريبي] صفحة تخصص (جلدية)',
            'category_id' => $dermCat,
            'header_mode' => 'default', 'footer_mode' => 'default',
            'cta_style' => 'scroll_booking', 'cta_label_ar' => 'اعرض العيادات',
        ];

        // 10) CITY #2 — Jeddah, minimal header + custom footer, DRAFT.
        $pages[] = [
            'type' => 'city', 'status' => 'draft',
            'slug' => 'demo-city-jeddah-clinics',
            'title_ar' => 'مجمّعات جدة الطبية',
            'title_en' => 'Jeddah Medical Complexes',
            'internal_name' => '[تجريبي] صفحة مدينة (جدة — مسودة)',
            'city_id' => $jeddah,
            'header_mode' => 'minimal', 'footer_mode' => 'custom',
            'footer_config' => [
                'about' => 'دليلك لأفضل المجمعات الطبية في جدة.',
                'copyright' => '© ' . date('Y') . ' دليل المجمعات الطبية',
                'whatsapp' => '966550000000',
                'social' => ['instagram' => 'https://instagram.com/example'],
            ],
            'cta_style' => 'scroll_booking', 'cta_label_ar' => 'اعرض المجمعات',
        ];

        foreach ($pages as $data) {
            $compareIds = $data['_compare'] ?? [];
            unset($data['_compare']);

            // Drop nulls so updateOrCreate doesn't overwrite with null on re-run
            // for optional links (e.g. a missing demo offer).
            $data = array_filter($data, fn ($v) => $v !== null);

            $data['created_by']  = $adminId;
            $data['in_sitemap']  = true;
            $data['meta_robots'] = 'index,follow';
            if (($data['status'] ?? null) === 'published') {
                $data['published_at'] = now();
            }

            $page = LandingPage::updateOrCreate(['slug' => $data['slug']], $data);

            // Seed the default block roster for this type (idempotent).
            $builder->seedDefaults($page);

            // Comparison pages link their clinics through the pivot.
            if ($compareIds) {
                $sync = [];
                foreach (array_values($compareIds) as $i => $clinicId) {
                    $sync[$clinicId] = ['sort_order' => ($i + 1) * 10, 'highlight' => $i === 0];
                }
                $page->clinics()->sync($sync);
            }
        }

        $this->command?->info('LandingPageSeeder: seeded ' . count($pages) . ' demo landing pages.');
    }
}
