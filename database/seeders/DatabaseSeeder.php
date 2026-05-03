<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Admin;
use App\Models\City;
use App\Models\Category;
use App\Models\Clinic;
use App\Models\SystemSetting;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAdmins();
        $this->seedCities();
        $this->seedCategories();
        $this->seedSystemSettings();
        $this->seedClinics();
    }

    private function seedAdmins(): void
    {
        Admin::updateOrCreate(
            ['email' => 'admin@saerha.sa'],
            [
                'name' => 'مدير النظام',
                'password' => bcrypt('password'),
                'role' => 'super_admin',
                'is_active' => true,
            ]
        );
    }

    private function seedCities(): void
    {
        $cities = [
            ['name' => 'الرياض', 'name_en' => 'Riyadh', 'sort_order' => 1],
            ['name' => 'جدة', 'name_en' => 'Jeddah', 'sort_order' => 2],
            ['name' => 'مكة المكرمة', 'name_en' => 'Makkah', 'sort_order' => 3],
            ['name' => 'المدينة المنورة', 'name_en' => 'Madinah', 'sort_order' => 4],
            ['name' => 'الدمام', 'name_en' => 'Dammam', 'sort_order' => 5],
            ['name' => 'الخبر', 'name_en' => 'Khobar', 'sort_order' => 6],
            ['name' => 'الظهران', 'name_en' => 'Dhahran', 'sort_order' => 7],
            ['name' => 'الطائف', 'name_en' => 'Taif', 'sort_order' => 8],
            ['name' => 'أبها', 'name_en' => 'Abha', 'sort_order' => 9],
            ['name' => 'القصيم', 'name_en' => 'Qassim', 'sort_order' => 10],
            ['name' => 'تبوك', 'name_en' => 'Tabuk', 'sort_order' => 11],
            ['name' => 'جازان', 'name_en' => 'Jazan', 'sort_order' => 12],
            ['name' => 'نجران', 'name_en' => 'Najran', 'sort_order' => 13],
            ['name' => 'حائل', 'name_en' => 'Hail', 'sort_order' => 14],
        ];

        foreach ($cities as $city) {
            City::updateOrCreate(['name' => $city['name']], $city);
        }
    }

    private function seedCategories(): void
    {
        $categories = [
            ['name' => 'أسنان', 'name_en' => 'Dentistry', 'emoji' => '🦷', 'sort_order' => 1],
            ['name' => 'جلدية وتجميل', 'name_en' => 'Dermatology', 'emoji' => '✨', 'sort_order' => 2],
            ['name' => 'عيون', 'name_en' => 'Ophthalmology', 'emoji' => '👁', 'sort_order' => 3],
            ['name' => 'نساء وولادة', 'name_en' => 'Gynecology', 'emoji' => '🌸', 'sort_order' => 4],
            ['name' => 'أطفال', 'name_en' => 'Pediatrics', 'emoji' => '👶', 'sort_order' => 5],
            ['name' => 'عظام ومفاصل', 'name_en' => 'Orthopedics', 'emoji' => '🦴', 'sort_order' => 6],
            ['name' => 'قلب وشرايين', 'name_en' => 'Cardiology', 'emoji' => '❤', 'sort_order' => 7],
            ['name' => 'باطنية', 'name_en' => 'Internal Medicine', 'emoji' => '🩺', 'sort_order' => 8],
            ['name' => 'أنف وأذن وحنجرة', 'name_en' => 'ENT', 'emoji' => '👂', 'sort_order' => 9],
            ['name' => 'نفسية وعصبية', 'name_en' => 'Psychiatry', 'emoji' => '🧠', 'sort_order' => 10],
            ['name' => 'تغذية علاجية', 'name_en' => 'Nutrition', 'emoji' => '🥗', 'sort_order' => 11],
            ['name' => 'علاج طبيعي', 'name_en' => 'Physical Therapy', 'emoji' => '💪', 'sort_order' => 12],
            ['name' => 'مختبرات', 'name_en' => 'Labs', 'emoji' => '🔬', 'sort_order' => 13],
            ['name' => 'أشعة', 'name_en' => 'Radiology', 'emoji' => '📡', 'sort_order' => 14],
        ];

        foreach ($categories as $cat) {
            Category::updateOrCreate(
                ['name_en' => $cat['name_en']],
                array_merge($cat, ['slug' => Str::slug($cat['name_en'])])
            );
        }
    }

    private function seedSystemSettings(): void
    {
        $settings = [
            ['key' => 'basic_subscription_price', 'value' => '300', 'type' => 'decimal', 'group' => 'subscriptions', 'label' => 'سعر الاشتراك الأساسي'],
            ['key' => 'premium_subscription_price', 'value' => '400', 'type' => 'decimal', 'group' => 'subscriptions', 'label' => 'سعر الاشتراك المميز'],
            ['key' => 'subscription_duration_days', 'value' => '90', 'type' => 'integer', 'group' => 'subscriptions', 'label' => 'مدة الاشتراك (أيام)'],
            ['key' => 'basic_articles_limit', 'value' => '5', 'type' => 'integer', 'group' => 'limits', 'label' => 'حد المقالات (أساسي/شهر)'],
            ['key' => 'otp_expiry_minutes', 'value' => '5', 'type' => 'integer', 'group' => 'auth', 'label' => 'مدة صلاحية OTP (دقائق)'],
            ['key' => 'platform_name', 'value' => 'سعرها', 'type' => 'string', 'group' => 'general', 'label' => 'اسم المنصة'],
            ['key' => 'platform_email', 'value' => 'info@saerha.sa', 'type' => 'string', 'group' => 'general', 'label' => 'البريد الرسمي'],
            ['key' => 'platform_phone', 'value' => '+966XXXXXXXXX', 'type' => 'string', 'group' => 'general', 'label' => 'رقم الهاتف الرسمي'],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }

    private function seedClinics(): void
    {
        $cities = City::all()->keyBy('name');
        $categories = Category::all();

        $clinicsData = [
            ['name' => 'مركز الرياض للأسنان', 'city' => 'الرياض', 'subscription_type' => 'premium', 'status' => 'active', 'is_featured' => true, 'cats' => ['Dentistry']],
            ['name' => 'عيادة الجمال والجلدية', 'city' => 'جدة', 'subscription_type' => 'basic', 'status' => 'active', 'is_featured' => false, 'cats' => ['Dermatology']],
            ['name' => 'مركز البصر للعيون', 'city' => 'الرياض', 'subscription_type' => 'premium', 'status' => 'active', 'is_featured' => true, 'cats' => ['Ophthalmology']],
            ['name' => 'عيادة أطفال المستقبل', 'city' => 'الدمام', 'subscription_type' => 'basic', 'status' => 'active', 'is_featured' => false, 'cats' => ['Pediatrics']],
            ['name' => 'مركز العظام والمفاصل', 'city' => 'الرياض', 'subscription_type' => 'premium', 'status' => 'active', 'is_featured' => true, 'cats' => ['Orthopedics']],
            ['name' => 'عيادة القلب التخصصية', 'city' => 'جدة', 'subscription_type' => 'basic', 'status' => 'pending', 'is_featured' => false, 'cats' => ['Cardiology']],
        ];

        foreach ($clinicsData as $data) {
            $city = $cities[$data['city']] ?? null;
            if (!$city) continue;

            $clinic = Clinic::updateOrCreate(
                ['email' => Str::slug($data['name']) . '@test.sa'],
                [
                    'name' => $data['name'],
                    'phone' => '05' . rand(10000000, 99999999),
                    'password' => bcrypt('password'),
                    'city_id' => $city->id,
                    'status' => $data['status'],
                    'subscription_type' => $data['subscription_type'],
                    'subscription_starts_at' => now()->subDays(30),
                    'subscription_ends_at' => now()->addDays(60),
                    'is_featured' => $data['is_featured'],
                    'description' => 'عيادة متخصصة تقدم أفضل الخدمات الطبية بأحدث التقنيات وأمهر الكوادر.',
                ]
            );

            $categoryIds = $categories->whereIn('name_en', $data['cats'])->pluck('id');
            $clinic->categories()->sync($categoryIds);
        }
    }
}
