<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Category;
use App\Models\City;
use App\Models\Clinic;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Default seeder — runs on `php artisan db:seed` and on
 * `php artisan migrate:refresh --seed`.
 *
 *   1. Canonical super-admin (admin@saerha.sa / password) for the panel.
 *   2. Cities + categories used everywhere else.
 *   3. System settings (subscriptions, limits, AI provider + encrypted keys).
 *   4. Six named clinics that match the README test credentials.
 *   5. DemoSeeder → tops every other table up to ≥20 demo rows for staging.
 *
 * The whole chain is idempotent: re-running converges instead of duplicating
 * or wiping the previously stored (encrypted) API keys.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAdmins();
        $this->seedCities();
        $this->seedCategories();
        $this->seedSystemSettings();
        $this->seedClinics();

        // Fill out every other table (users, services, articles, bookings,
        // quotes, reviews, subscriptions, stats, leads, complaints, audit log,
        // notifications, …) so the platform looks alive on a fresh install.
        $this->call(DemoSeeder::class);
    }

    private function seedAdmins(): void
    {
        Admin::updateOrCreate(
            ['email' => 'admin@saerha.sa'],
            [
                'name'      => 'مدير النظام',
                'password'  => bcrypt('password'),
                'role'      => 'super_admin',
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
            ['key' => 'subscription_reminder_days', 'value' => '10', 'type' => 'integer', 'group' => 'subscriptions', 'label' => 'أيام التذكير قبل انتهاء الاشتراك'],
            ['key' => 'basic_articles_limit', 'value' => '5', 'type' => 'integer', 'group' => 'limits', 'label' => 'حد المقالات (أساسي/شهر)'],
            ['key' => 'otp_expiry_minutes', 'value' => '5', 'type' => 'integer', 'group' => 'auth', 'label' => 'مدة صلاحية OTP (دقائق)'],
            ['key' => 'platform_name', 'value' => 'دليل المجمعات الطبية', 'type' => 'string', 'group' => 'general', 'label' => 'اسم المنصة'],
            ['key' => 'platform_email', 'value' => 'info@saerha.sa', 'type' => 'string', 'group' => 'general', 'label' => 'البريد الرسمي'],
            ['key' => 'platform_phone', 'value' => '+966XXXXXXXXX', 'type' => 'string', 'group' => 'general', 'label' => 'رقم الهاتف الرسمي'],

            // ----- AI assistant (provider + per-provider key + model) -----
            // ai_provider selects the active LLM. The matching ai_<name>_api_key
            // gets used. Keys are type=encrypted so they're never returned in
            // plaintext from the API (the React UI shows them masked).
            ['key' => 'ai_provider',          'value' => 'gemini',                 'type' => 'string',    'group' => 'ai', 'label' => 'مزود الذكاء الاصطناعي',  'description' => 'القيمة المسموحة: gemini أو openai أو anthropic. الافتراضي gemini (Gemini 2.5 Flash).'],
            ['key' => 'ai_gemini_api_key',    'value' => null,                     'type' => 'encrypted', 'group' => 'ai', 'label' => 'مفتاح Gemini API',       'description' => 'أنشئ المفتاح من Google AI Studio: https://aistudio.google.com/app/apikey'],
            ['key' => 'ai_gemini_model',      'value' => 'gemini-2.5-flash',       'type' => 'string',    'group' => 'ai', 'label' => 'موديل Gemini',           'description' => 'مثال: gemini-2.5-flash (سريع وموفّر) أو gemini-2.5-pro (أذكى).'],
            ['key' => 'ai_openai_api_key',    'value' => null,                     'type' => 'encrypted', 'group' => 'ai', 'label' => 'مفتاح OpenAI API',       'description' => 'أنشئ المفتاح من: https://platform.openai.com/api-keys'],
            ['key' => 'ai_openai_model',      'value' => 'gpt-4o-mini',            'type' => 'string',    'group' => 'ai', 'label' => 'موديل OpenAI',           'description' => 'مثال: gpt-4o-mini (موفّر) أو gpt-4o.'],
            ['key' => 'ai_anthropic_api_key', 'value' => null,                     'type' => 'encrypted', 'group' => 'ai', 'label' => 'مفتاح Anthropic API',    'description' => 'أنشئ المفتاح من: https://console.anthropic.com/settings/keys'],
            ['key' => 'ai_anthropic_model',   'value' => 'claude-sonnet-4-6',      'type' => 'string',    'group' => 'ai', 'label' => 'موديل Anthropic',        'description' => 'مثال: claude-sonnet-4-6 أو claude-haiku-4-5-20251001.'],

            // ----- AI conversational behaviour (system prompt + guardrails) -----
            // These knobs let the super-admin steer the assistant without code changes.
            // The hard safety footer (no medical advice, no inventing clinics) lives
            // in AiAssistantService and is NOT configurable here on purpose.
            ['key' => 'ai_freeform_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'ai', 'label' => 'تفعيل المحادثة الذكيّة',  'description' => 'عند التفعيل، يجيب المساعد على أي سؤال (وليس فقط البحث عن العيادات) عبر LLM المختار. عند التعطيل، يرجع لردود قوالب جامدة.'],
            ['key' => 'ai_assistant_name',   'value' => 'سلمى', 'type' => 'string', 'group' => 'ai', 'label' => 'اسم المساعد',            'description' => 'الاسم الذي يعرّف به المساعد نفسه ويظهر في الواجهة. اقتراحات لشخصية الاستقبال الدافئة: سلمى، نور، ريان.'],
            ['key' => 'ai_max_tokens',       'value' => '800', 'type' => 'integer',         'group' => 'ai', 'label' => 'الحدّ الأقصى للرموز (output tokens)', 'description' => 'كم رمزاً يُسمح للمساعد بإنتاجه في كل رد. 200=قصير جداً، 800=مناسب لمحادثة، 1500=ردود مفصّلة.'],
            ['key' => 'ai_temperature',      'value' => '0.5', 'type' => 'decimal',         'group' => 'ai', 'label' => 'درجة الحرارة (0–1)', 'description' => 'مدى الإبداع: 0.0 ردود ثابتة ودقيقة، 0.7 إبداع متوازن، 1.0 ردود متنوّعة قد تكون غير متّسقة. الموصى به 0.4–0.6.'],
            ['key' => 'ai_system_prompt',    'value' => null,  'type' => 'text',            'group' => 'ai', 'label' => 'برومت النظام (Persona)', 'description' => 'الشخصيّة الكاملة التي يتقمّصها المساعد. اتركه فارغاً لاستخدام الافتراضي الدافئ المُضمَّن (شخصيّة استقبال طبية متفهمة). غيِّر هنا لتعديل الأسلوب أو إضافة معلومات عن المنصّة.'],
            ['key' => 'ai_restrictions',     'value' => null,  'type' => 'text',            'group' => 'ai', 'label' => 'قيود إضافيّة على الردّ',  'description' => 'قيود تُضاف فوق الـ HARD SAFETY الثابت. أمثلة: "لا تذكر أسعاراً تقديرية"، "اقترح دائماً قراءة سياسة الخصوصيّة"، "اطلب من العميل تسجيل الدخول لطلب عرض سعر".'],

            // Emergency-routing hotlines — surfaced verbatim in the deterministic
            // (non-LLM) reply when red-line phrases are detected. Saudi defaults;
            // adjust per region. NEVER blank these out — the layer falls back to
            // the original hard-coded values if a setting is empty.
            ['key' => 'ai_emergency_ambulance',      'value' => '997',       'type' => 'string', 'group' => 'ai', 'label' => 'رقم الإسعاف (طوارئ طبية)', 'description' => 'الرقم الذي يعرضه المساعد فوراً عند اكتشاف أعراض طارئة (ألم صدري شديد، ضيق تنفس مفاجئ، نزيف حاد، فقدان وعي). الافتراضي 997 للهلال الأحمر السعودي.'],
            ['key' => 'ai_emergency_mental_support', 'value' => '920033360', 'type' => 'string', 'group' => 'ai', 'label' => 'خط الدعم النفسي',       'description' => 'الرقم الذي يعرضه المساعد فوراً عند اكتشاف لغة إيذاء النفس أو الانتحار. الافتراضي 920033360 (خط الدعم النفسي السعودي، 24/7 ومجاني).'],
        ];

        foreach ($settings as $setting) {
            // For encrypted slots we only insert defaults the first time —
            // re-running the seeder must not clobber a stored API key. For
            // every other type updateOrCreate is fine (it refreshes labels).
            if (($setting['type'] ?? null) === 'encrypted') {
                SystemSetting::firstOrCreate(['key' => $setting['key']], $setting);
            } else {
                SystemSetting::updateOrCreate(['key' => $setting['key']], $setting);
            }
        }
    }

    /**
     * Six named clinics that match the README test credentials. The slug is
     * built from a stable ASCII id (not the Arabic name) because Str::slug
     * strips non-Latin characters — that was the root cause of the previous
     * regression where all six rows collided on `@test.sa` and only the last
     * one survived.
     */
    private function seedClinics(): void
    {
        $cities     = City::all()->keyBy('name');
        $categories = Category::all();

        $clinicsData = [
            ['id' => 'riyadh-dental',        'name' => 'مركز الرياض للأسنان',     'city' => 'الرياض', 'subscription_type' => 'premium', 'status' => 'active',  'is_featured' => true,  'cats' => ['Dentistry']],
            ['id' => 'jeddah-dermatology',   'name' => 'مجمع الجمال والجلدية',     'city' => 'جدة',    'subscription_type' => 'basic',   'status' => 'active',  'is_featured' => false, 'cats' => ['Dermatology']],
            ['id' => 'riyadh-ophthalmology', 'name' => 'مركز البصر للعيون',       'city' => 'الرياض', 'subscription_type' => 'premium', 'status' => 'active',  'is_featured' => true,  'cats' => ['Ophthalmology']],
            ['id' => 'dammam-pediatrics',    'name' => 'مجمع أطفال المستقبل',      'city' => 'الدمام', 'subscription_type' => 'basic',   'status' => 'active',  'is_featured' => false, 'cats' => ['Pediatrics']],
            ['id' => 'riyadh-orthopedics',   'name' => 'مركز العظام والمفاصل',     'city' => 'الرياض', 'subscription_type' => 'premium', 'status' => 'active',  'is_featured' => true,  'cats' => ['Orthopedics']],
            ['id' => 'jeddah-cardiology',    'name' => 'مجمع القلب التخصصي',       'city' => 'جدة',    'subscription_type' => 'basic',   'status' => 'pending', 'is_featured' => false, 'cats' => ['Cardiology']],
        ];

        foreach ($clinicsData as $i => $data) {
            $city = $cities[$data['city']] ?? null;
            if (! $city) continue;

            $clinic = Clinic::updateOrCreate(
                ['email' => $data['id'] . '@test.sa'],
                [
                    'name'                   => $data['name'],
                    'slug'                   => $data['id'],
                    // Deterministic, unique phone numbers so the README's
                    // login table stays stable across re-seeds.
                    'phone'                  => '055000' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                    'password'               => bcrypt('password'),
                    'city_id'                => $city->id,
                    'status'                 => $data['status'],
                    'subscription_type'      => $data['subscription_type'],
                    'subscription_starts_at' => now()->subDays(30),
                    'subscription_ends_at'   => now()->addDays(60),
                    'is_featured'            => $data['is_featured'],
                    'description'            => 'مجمع متخصص يقدم أفضل الخدمات الطبية بأحدث التقنيات وأمهر الكوادر.',
                ]
            );

            $categoryIds = $categories->whereIn('name_en', $data['cats'])->pluck('id');
            $clinic->categories()->sync($categoryIds);
        }
    }
}
