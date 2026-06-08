<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\Subscription;
use App\Models\SubscriptionPackage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds three editable default packages — Free / Standard / Premium —
 * and back-fills the existing clinic + subscription rows so nothing
 * breaks on the day this migration lands.
 *
 * The legacy `clinics.subscription_type` string was either 'basic',
 * 'premium', or null. Mapping:
 *   basic  → standard (paid 300, sage)
 *   premium → premium (paid 500, gold)
 *   null    → free
 *
 * Idempotent: re-running upserts the three rows on slug + only
 * back-fills clinics whose `subscription_package_id` is still null.
 */
class SubscriptionPackagesSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'slug'         => SubscriptionPackage::SLUG_FREE,
                'name_ar'      => 'مجانية',
                'name_en'      => 'Free',
                'tagline_ar'   => 'ابدأ مجاناً وعرّف بمجمعك',
                'tagline_en'   => 'Get started for free',
                'color_token'  => 'gray',
                'monthly_price'=> 0,
                'sort_order'   => 10,
                'is_active'    => true,
                // limits + flags
                'services_limit'              => 10,
                'articles_monthly_limit'      => 2,
                'ai_article_generation'       => false,
                'featured_in_search'          => false,
                'ai_assistant_priority'       => 0,
                'google_reviews_sync'         => false,
                'verified_badge'              => false,
                'analytics_level'             => SubscriptionPackage::ANALYTICS_BASIC,
                'quote_replies_monthly_limit' => 3,
                'banner_slots'                => 0,
                'allow_offers_packages'       => false,
                'allow_doctors_before_after'  => false,
                'crm_enabled'                 => true,
            ],
            [
                'slug'         => SubscriptionPackage::SLUG_STANDARD,
                'name_ar'      => 'مدفوعة',
                'name_en'      => 'Standard',
                'tagline_ar'   => 'أدوات احترافية لإدارة عيادتك',
                'tagline_en'   => 'Pro tools for a serious clinic',
                'color_token'  => 'sage',
                'monthly_price'=> 300,
                'sort_order'   => 20,
                'is_active'    => true,
                'services_limit'              => 30,
                'articles_monthly_limit'      => 10,
                'ai_article_generation'       => true,
                'featured_in_search'          => false,
                'ai_assistant_priority'       => 1,
                'google_reviews_sync'         => true,
                'verified_badge'              => true,
                'analytics_level'             => SubscriptionPackage::ANALYTICS_FULL,
                'quote_replies_monthly_limit' => 30,
                'banner_slots'                => 1,
                'allow_offers_packages'       => true,
                'allow_doctors_before_after'  => true,
                'crm_enabled'                 => true,
            ],
            [
                'slug'         => SubscriptionPackage::SLUG_PREMIUM,
                'name_ar'      => 'مميزة',
                'name_en'      => 'Premium',
                'tagline_ar'   => 'ظهور قصوى وحدود مفتوحة',
                'tagline_en'   => 'Maximum visibility, no limits',
                'color_token'  => 'gold',
                'monthly_price'=> 500,
                'sort_order'   => 30,
                'is_active'    => true,
                'services_limit'              => null,  // unlimited
                'articles_monthly_limit'      => null,  // unlimited
                'ai_article_generation'       => true,
                'featured_in_search'          => true,
                'ai_assistant_priority'       => 2,
                'google_reviews_sync'         => true,
                'verified_badge'              => true,
                'analytics_level'             => SubscriptionPackage::ANALYTICS_FULL,
                'quote_replies_monthly_limit' => null,  // unlimited
                'banner_slots'                => 3,
                'allow_offers_packages'       => true,
                'allow_doctors_before_after'  => true,
                'crm_enabled'                 => true,
            ],
        ];

        foreach ($packages as $pkg) {
            SubscriptionPackage::updateOrCreate(['slug' => $pkg['slug']], $pkg);
        }

        $free     = SubscriptionPackage::where('slug', SubscriptionPackage::SLUG_FREE)->first();
        $standard = SubscriptionPackage::where('slug', SubscriptionPackage::SLUG_STANDARD)->first();
        $premium  = SubscriptionPackage::where('slug', SubscriptionPackage::SLUG_PREMIUM)->first();

        // ── Back-fill clinics ──────────────────────────────────────────
        // Legacy `subscription_type` values map to the new packages.
        // Only clinics whose package is still null are touched — so
        // re-running this seeder doesn't trample manual admin changes.
        $mapped = [
            'basic'   => $standard->id,
            'premium' => $premium->id,
        ];
        foreach ($mapped as $legacy => $packageId) {
            Clinic::where('subscription_type', $legacy)
                ->whereNull('subscription_package_id')
                ->update(['subscription_package_id' => $packageId]);
        }
        Clinic::whereNull('subscription_package_id')
            ->update(['subscription_package_id' => $free->id, 'subscription_type' => SubscriptionPackage::SLUG_FREE]);

        // ── Back-fill subscriptions ────────────────────────────────────
        // `type` was 'basic' or 'premium'. Mirror to the new FK so
        // historical rows are queryable through `package()` going forward.
        Subscription::where('type', 'basic')
            ->whereNull('subscription_package_id')
            ->update(['subscription_package_id' => $standard->id]);
        Subscription::where('type', 'premium')
            ->whereNull('subscription_package_id')
            ->update(['subscription_package_id' => $premium->id]);

        $this->command?->info(
            'SubscriptionPackagesSeeder: 3 packages seeded + ' .
            DB::table('clinics')->whereNotNull('subscription_package_id')->count() .
            ' clinics tied to a package.',
        );
    }
}
