<?php

namespace Database\Seeders;

use App\Models\NavigationLink;
use App\Models\StaticPage;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

/**
 * Foundation rows for the public site chrome:
 *   1. The 5 core static pages (About/Privacy/Terms/Contact/FAQ) — is_system,
 *      so the admin can edit but not delete them.
 *   2. Default header + footer navigation links (only when the table is empty,
 *      so re-running never clobbers admin edits / reordering).
 *   3. Footer/contact SystemSetting rows (WhatsApp + social + footer about).
 *
 * Idempotent: pages upsert on slug; nav links seed-once; settings updateOrCreate.
 */
class StaticContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPages();
        $this->seedNavigationLinks();
        $this->seedFooterSettings();
    }

    private function seedPages(): void
    {
        $pages = [
            [
                'slug'     => 'about',
                'title_ar' => 'من نحن',
                'title_en' => 'About Us',
                'body_ar'  => '<p>دليل المجمعات الطبية منصة متخصصة تساعدك على البحث عن المجمعات والعيادات الطبية في المملكة ومقارنتها والوصول إلى خدماتها بسهولة.</p>',
                'body_en'  => '<p>A specialized platform that helps you search, compare, and reach medical complexes and clinics across the Kingdom with ease.</p>',
                'sort_order' => 10,
            ],
            [
                'slug'     => 'privacy',
                'title_ar' => 'سياسة الخصوصية',
                'title_en' => 'Privacy Policy',
                'body_ar'  => '<p>نحرص على حماية خصوصيتك. توضّح هذه الصفحة كيف نجمع بياناتك ونستخدمها ونحميها.</p>',
                'body_en'  => '<p>We are committed to protecting your privacy. This page explains how we collect, use, and protect your data.</p>',
                'sort_order' => 20,
            ],
            [
                'slug'     => 'terms',
                'title_ar' => 'الشروط والأحكام',
                'title_en' => 'Terms & Conditions',
                'body_ar'  => '<p>باستخدامك للمنصة فإنك توافق على الشروط والأحكام الموضّحة في هذه الصفحة.</p>',
                'body_en'  => '<p>By using the platform you agree to the terms and conditions described on this page.</p>',
                'sort_order' => 30,
            ],
            [
                'slug'     => 'contact',
                'title_ar' => 'اتصل بنا',
                'title_en' => 'Contact Us',
                'body_ar'  => '<p>يسعدنا تواصلك معنا عبر بيانات الاتصال الرسمية الموضّحة في تذييل الموقع.</p>',
                'body_en'  => '<p>We are happy to hear from you through the official contact details shown in the site footer.</p>',
                'sort_order' => 40,
            ],
            [
                'slug'     => 'faq',
                'title_ar' => 'الأسئلة الشائعة',
                'title_en' => 'FAQ',
                'body_ar'  => '<p>إجابات على الأسئلة الأكثر شيوعاً حول استخدام المنصة وخدماتها.</p>',
                'body_en'  => '<p>Answers to the most frequently asked questions about using the platform and its services.</p>',
                'sort_order' => 50,
            ],
        ];

        foreach ($pages as $page) {
            StaticPage::updateOrCreate(
                ['slug' => $page['slug']],
                array_merge($page, [
                    'is_active'    => true,
                    'is_system'    => true,
                    'published_at' => now(),
                ]),
            );
        }
    }

    private function seedNavigationLinks(): void
    {
        // Seed-once guard: never clobber admin edits / reordering on re-run.
        if (NavigationLink::query()->exists()) {
            return;
        }

        $pages = StaticPage::pluck('id', 'slug');

        $links = [
            // Header — keep the original nav identical out of the box.
            ['location' => 'header', 'footer_column' => null, 'label_ar' => 'ابحث عن مجمع', 'label_en' => 'Find a complex', 'route_name' => 'search', 'sort_order' => 10],
            ['location' => 'header', 'footer_column' => null, 'label_ar' => 'عروض الأسعار', 'label_en' => 'Price quotes', 'route_name' => 'quotes.board', 'sort_order' => 20],

            // Footer column 1 — quick links.
            ['location' => 'footer', 'footer_column' => 1, 'label_ar' => 'الرئيسية', 'label_en' => 'Home', 'route_name' => 'home', 'sort_order' => 10],
            ['location' => 'footer', 'footer_column' => 1, 'label_ar' => 'ابحث عن مجمع', 'label_en' => 'Find a complex', 'route_name' => 'search', 'sort_order' => 20],

            // Footer column 2 — company.
            ['location' => 'footer', 'footer_column' => 2, 'label_ar' => 'من نحن', 'label_en' => 'About Us', 'static_page_id' => $pages['about'] ?? null, 'sort_order' => 10],
            ['location' => 'footer', 'footer_column' => 2, 'label_ar' => 'اتصل بنا', 'label_en' => 'Contact Us', 'static_page_id' => $pages['contact'] ?? null, 'sort_order' => 20],
            ['location' => 'footer', 'footer_column' => 2, 'label_ar' => 'الأسئلة الشائعة', 'label_en' => 'FAQ', 'static_page_id' => $pages['faq'] ?? null, 'sort_order' => 30],

            // Footer column 3 — legal.
            ['location' => 'footer', 'footer_column' => 3, 'label_ar' => 'سياسة الخصوصية', 'label_en' => 'Privacy Policy', 'static_page_id' => $pages['privacy'] ?? null, 'sort_order' => 10],
            ['location' => 'footer', 'footer_column' => 3, 'label_ar' => 'الشروط والأحكام', 'label_en' => 'Terms & Conditions', 'static_page_id' => $pages['terms'] ?? null, 'sort_order' => 20],
        ];

        foreach ($links as $link) {
            NavigationLink::create(array_merge([
                'is_active'    => true,
                'open_new_tab' => false,
            ], $link));
        }
    }

    private function seedFooterSettings(): void
    {
        $settings = [
            // Official contact (platform_email / platform_phone already exist
            // in the general group — we add WhatsApp here).
            ['key' => 'platform_whatsapp', 'value' => '966564844382', 'type' => 'string', 'group' => 'general', 'label' => 'رقم واتساب الرسمي', 'description' => 'يُعرض في تذييل الموقع كزر تواصل عبر واتساب. أدخل الرقم بصيغة دولية بدون + أو مسافات، مثال: 9665XXXXXXXX.'],

            // Footer about blurb (overrides the static @lang fallback).
            ['key' => 'footer_about_ar', 'value' => 'منصة متخصصة للبحث والمقارنة وحجز الخدمات الطبية في المملكة العربية السعودية.', 'type' => 'text', 'group' => 'footer', 'label' => 'نبذة التذييل (عربي)', 'description' => 'النص التعريفي القصير الذي يظهر في تذييل الموقع بجانب الشعار.'],
            ['key' => 'footer_about_en', 'value' => 'A specialized platform to search, compare, and book medical services in Saudi Arabia.', 'type' => 'text', 'group' => 'footer', 'label' => 'نبذة التذييل (إنجليزي)', 'description' => 'النص التعريفي القصير بالإنجليزية.'],

            // Social handles/links (full URLs). Empty = hidden in the footer.
            ['key' => 'social_instagram', 'value' => null, 'type' => 'string', 'group' => 'footer', 'label' => 'رابط إنستقرام', 'description' => 'الرابط الكامل لحساب إنستقرام الرسمي. اتركه فارغاً لإخفاء الأيقونة.'],
            ['key' => 'social_twitter',   'value' => null, 'type' => 'string', 'group' => 'footer', 'label' => 'رابط X (تويتر)', 'description' => 'الرابط الكامل لحساب X الرسمي. اتركه فارغاً لإخفاء الأيقونة.'],
            ['key' => 'social_snapchat',  'value' => null, 'type' => 'string', 'group' => 'footer', 'label' => 'رابط سناب شات', 'description' => 'الرابط الكامل لحساب سناب شات الرسمي. اتركه فارغاً لإخفاء الأيقونة.'],
            ['key' => 'social_tiktok',    'value' => null, 'type' => 'string', 'group' => 'footer', 'label' => 'رابط تيك توك', 'description' => 'الرابط الكامل لحساب تيك توك الرسمي. اتركه فارغاً لإخفاء الأيقونة.'],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
