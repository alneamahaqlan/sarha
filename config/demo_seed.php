<?php

use Database\Seeders\BookingsCrmSeeder;
use Database\Seeders\ClinicTeamSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\EdgeCaseScenarioSeeder;
use Database\Seeders\LandingPageSeeder;
use Database\Seeders\MassiveCityCoverageSeeder;
use Database\Seeders\RelativesAndProxyBookingsSeeder;
use Database\Seeders\UserActivityPatternsSeeder;
use Database\Seeders\YearOfUsageSeeder;

/*
|--------------------------------------------------------------------------
| Demo-seed / "Seeder Center" configuration
|--------------------------------------------------------------------------
|
| Single source of truth for the Seeder Center feature (مركز السيدر). The
| migration, the snapshot-and-stamp helper (App\Support\DemoBatch), the
| HasDemoData trait, and the admin controller all read from here.
|
| Core idea: every seeded row carries `is_demo = true` + a `demo_batch`
| label that says which seeder created it. Real data entered through the
| app stays `is_demo = false` / `demo_batch = null` and is NEVER touched
| by any Seeder-Center operation. `demo_hidden_at` powers the soft-hide
| (instant hide/restore) on the catalogue tables.
|
| Deliberately NOT stamped (foundation / always protected):
|   cities, categories, admins, system_settings, whatsapp_senders,
|   subscription_packages, homepage_*, static_pages, navigation_links,
|   and the six named login-fixture clinics from DatabaseSeeder::seedClinics.
|
*/

return [

    /*
    | Every table that receives stampable demo rows. The migration adds the
    | three demo columns to each, the stamper writes them, and "purge"
    | (hard delete) targets them. Only tables with an auto-increment `id`
    | belong here — pivots are handled via their parent (see 'pivots').
    */
    'tables' => [
        // ── Catalogue / content (also soft-hideable, see 'hideable') ──
        'clinics',
        'sub_clinics',
        'services',
        'offers',
        'doctors',
        'packages',
        'articles',
        'google_reviews',
        'before_after_photos',
        // ── Transactional / CRM (purge-only, no global hide scope) ──
        'bookings',
        'complaints',
        'price_quote_requests',
        'price_quote_replies',
        'customers',
        'category_requests',
        'sales_leads',
        'relatives',
        // ── People & supporting demo data (purge-only) ──
        'users',
        'subscriptions',
        'clinic_working_hours',
        'clinic_stats',
        'clinic_team_members',
        'clinic_activity_logs',
        'customer_tags',
        'booking_tags',
        'ai_conversations',
        'otp_codes',
        'audit_logs',
        'platform_notifications',
        'user_activity_events',
        'user_visit_sessions',
        'clinic_impressions',
        'service_impressions',
        // ── Marketing landing pages (soft-hideable; blocks are children) ──
        'landing_pages',
        'landing_page_blocks',
    ],

    /*
    | Tables that ALSO get a global "hide" scope via HasDemoData. Soft-hiding
    | a batch flips demo_hidden_at on these rows so they vanish from the whole
    | platform instantly (and unhide brings them back, same data, no reseed).
    | Restricted to top-level catalogue entities whose disappearance is clean
    | (no dangling UI). Transactional tables are purge-only on purpose.
    */
    'hideable' => [
        'clinics',
        'sub_clinics',
        'services',
        'offers',
        'doctors',
        'packages',
        'articles',
        'google_reviews',
        'before_after_photos',
        'landing_pages',
    ],

    /*
    | Pivot / link tables with no own `id`. Not stamped; instead, on purge we
    | delete pivot rows whose parent row is part of the purge set.
    |   pivot_table => [parent_key_on_pivot => parent_table]
    */
    'pivots' => [
        'clinic_categories'        => ['clinic_id'  => 'clinics'],
        'category_service'         => ['service_id' => 'services'],
        'package_service'          => ['package_id' => 'packages'],
        'price_quote_request_city' => ['price_quote_request_id' => 'price_quote_requests'],
        'favorites'                => ['clinic_id'  => 'clinics'],
        'landing_page_clinics'     => ['landing_page_id' => 'landing_pages'],
    ],

    /*
    | FK-entanglement guard. Before purging a batch we check whether any REAL
    | (is_demo = false) child row points at a demo row that is about to be
    | deleted. If so we BLOCK the purge and report the conflicts instead of
    | cascading / orphaning (the user's chosen policy).
    |   child_table => [fk_column => parent_table]
    */
    'conflicts' => [
        'bookings' => [
            'clinic_id'  => 'clinics',
            'service_id' => 'services',
            'offer_id'   => 'offers',
            'package_id' => 'packages',
        ],
        'complaints' => [
            'clinic_id' => 'clinics',
        ],
        'price_quote_requests' => [
            'clinic_id' => 'clinics',
        ],
        'favorites' => [
            'clinic_id' => 'clinics',
        ],
    ],

    /*
    | The demo batches surfaced in the Seeder Center, in dependency order.
    | Each maps to exactly one re-runnable seeder class.
    |   seeder => artisan db:seed --class target for "reseed"
    |   heavy  => true seeders run async via a queued job (minutes-long)
    */
    'batches' => [
        'demo' => [
            'seeder'   => DemoSeeder::class,
            'heavy'    => false,
            'label_ar' => 'بيانات العرض الأساسية',
            'label_en' => 'Core demo data',
            'desc_ar'  => 'مجموعة أولية صغيرة من المجمعات والخدمات والحجوزات والمقالات، تُعبّئ الموقع عند أول تشغيل ليبدو نشِطاً.',
            'desc_en'  => 'A small starter set of complexes, services, bookings and articles that fills the site on first run.',
        ],
        'massive_city' => [
            'seeder'   => MassiveCityCoverageSeeder::class,
            'heavy'    => true,
            'label_ar' => 'التغطية الموسّعة للمدن',
            'label_en' => 'Massive city coverage',
            'desc_ar'  => 'عدد كبير من المجمعات التجريبية موزّعة على كل المدن مع آلاف الخدمات — لإظهار الموقع ممتلئاً في كل منطقة.',
            'desc_en'  => 'Many demo complexes spread across every city with thousands of services — makes the site look full everywhere.',
        ],
        'year_of_usage' => [
            'seeder'   => YearOfUsageSeeder::class,
            'heavy'    => true,
            'label_ar' => 'سنة استخدام كاملة',
            'label_en' => 'A year of usage',
            'desc_ar'  => 'سنة كاملة من الحجوزات والتقييمات وطلبات الأسعار والإحصائيات، كأن الموقع يعمل منذ سنة. (أكبر مجموعة)',
            'desc_en'  => 'A full year of bookings, reviews, price quotes and stats — as if the site has been running for a year.',
        ],
        'edge_cases' => [
            'seeder'   => EdgeCaseScenarioSeeder::class,
            'heavy'    => true,
            'label_ar' => 'حالات حدّية وعروض خاصة',
            'label_en' => 'Edge-case scenarios',
            'desc_ar'  => 'أمثلة خاصة: صور قبل/بعد، عروض بخصومات كبيرة، حجوزات في المستقبل، وطلبات تصنيفات جديدة.',
            'desc_en'  => 'Special examples: before/after photos, deep-discount offers, future bookings, and new-category requests.',
        ],
        'relatives_proxy' => [
            'seeder'   => RelativesAndProxyBookingsSeeder::class,
            'heavy'    => false,
            'label_ar' => 'الأقارب والحجوزات بالنيابة',
            'label_en' => 'Relatives & proxy bookings',
            'desc_ar'  => 'أقارب محفوظون وحجوزات تمت بالنيابة عن شخص آخر — لتجربة ميزة «الحجز لأحد أقاربي».',
            'desc_en'  => 'Saved relatives and bookings made on someone else\'s behalf — to test the "book for a relative" feature.',
        ],
        'user_activity' => [
            'seeder'   => UserActivityPatternsSeeder::class,
            'heavy'    => false,
            'label_ar' => 'أنماط نشاط المستخدمين',
            'label_en' => 'User activity patterns',
            'desc_ar'  => 'مستخدمون تجريبيون بسلوكيات مختلفة (نشِط، متردّد…) مع سجل زياراتهم — لتجربة شاشة ملف المستخدم.',
            'desc_en'  => 'Demo users with different behaviours and visit histories — to test the user-profile screen.',
        ],
        'clinic_team' => [
            'seeder'   => ClinicTeamSeeder::class,
            'heavy'    => false,
            'label_ar' => 'فرق المجمعات وسجل النشاط',
            'label_en' => 'Clinic teams & activity',
            'desc_ar'  => 'أعضاء فريق تجريبيون داخل المجمعات مع سجل نشاطهم — لتجربة شاشتَي «فريقي» و«نشاط الفريق».',
            'desc_en'  => 'Demo team members inside complexes with their activity log — to test the team screens.',
        ],
        'crm' => [
            'seeder'   => BookingsCrmSeeder::class,
            'heavy'    => false,
            'label_ar' => 'بيانات CRM للحجوزات',
            'label_en' => 'Bookings CRM data',
            'desc_ar'  => 'وسوم وتصنيفات للعملاء وموظفون مسؤولون عن الحجوزات وعملاء مميّزون — لتجربة لوحة إدارة الحجوزات (الكانبان).',
            'desc_en'  => 'Customer tags, VIP customers and booking assignees — to test the bookings Kanban board.',
        ],
        'landing_pages' => [
            'seeder'   => LandingPageSeeder::class,
            'heavy'    => false,
            'label_ar' => 'صفحات الهبوط التسويقية',
            'label_en' => 'Marketing landing pages',
            'desc_ar'  => '١٠ صفحات هبوط متنوّعة (مجمع، عرض، مدينة، تخصص، مقارنة، مخصّصة) بأنماط هيدر/فوتر مختلفة — لتجربة بانية صفحات الهبوط وإحصاءاتها.',
            'desc_en'  => '10 varied landing pages (clinic, offer, city, category, comparison, custom) with different header/footer styles — to test the landing-page builder and analytics.',
        ],
    ],
];
