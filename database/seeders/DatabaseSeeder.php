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
 * `php artisan migrate:fresh --seed`.
 *
 * Order matters; each seeder below depends on the ones above it:
 *   1. seedAdmins — super-admin login (admin@saerha.sa / password)
 *   2. seedCities + seedCategories — required by clinics, sections, lookups
 *   3. seedSystemSettings — subscriptions, AI provider, encrypted API keys
 *   4. seedClinics — six named clinics matching the README test credentials
 *   5. DemoSeeder — tops every other table to ≥20 demo rows
 *   6. HomepageSectionsSeeder — the 16 CMS rows the public homepage loops over
 *      (REQUIRED — without these the homepage renders empty)
 *   7. MassiveCityCoverageSeeder — 30 clinics per active city + 13k+ services
 *   8. BackfillClinicCoordinatesSeeder — lat/lng for any clinic missing them
 *   9. YearOfUsageSeeder — a year of bookings/reviews/quotes/stats etc.
 *  10. EdgeCaseScenarioSeeder — before/after photos, category requests,
 *      deep-discount offers, future bookings
 *
 * The whole chain is idempotent: re-running converges instead of duplicating
 * or wiping the previously stored (encrypted) API keys.
 *
 * Heavy seeders (7, 9, 10) generate tens of thousands of rows and can take
 * several minutes. Set SEED_HEAVY=0 in .env to skip them (CI, hotfix deploys
 * where you only want the foundation rows).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Foundation: required for the app to function ───────────────────
        $this->seedAdmins();
        $this->seedCities();
        $this->seedCategories();
        $this->seedSystemSettings();
        $this->seedWhatsAppSenders();
        $this->seedClinics();

        // Fill out every other table (users, services, articles, bookings,
        // quotes, reviews, subscriptions, stats, leads, complaints, audit log,
        // notifications, …) so the platform looks alive on a fresh install.
        $this->call(DemoSeeder::class);

        // The 16 homepage sections + 3 demo banner slides — REQUIRED, the
        // public landing page renders empty without these rows.
        $this->call(HomepageSectionsSeeder::class);

        // ── Heavy demo data — opt out via SEED_HEAVY=0 in .env ─────────────
        if (env('SEED_HEAVY', true)) {
            $this->call(MassiveCityCoverageSeeder::class);
            $this->call(BackfillClinicCoordinatesSeeder::class);
            $this->call(YearOfUsageSeeder::class);
            $this->call(EdgeCaseScenarioSeeder::class);
        }

        // Subscription packages (Free/Standard/Premium) + back-fill of
        // existing clinics & subscriptions to the new package FKs.
        // Idempotent — re-running upserts the 3 rows on slug + only
        // touches clinics whose package is still null.
        $this->call(SubscriptionPackagesSeeder::class);

        // Saved relatives + a few proxy bookings so the "حجز لأحد أقاربي"
        // filter and the clinic-side "بالنيابة" badge have data to render.
        // Lives outside the heavy block — light, idempotent, always wanted.
        $this->call(RelativesAndProxyBookingsSeeder::class);

        // 5 behavioural archetypes × 4 users so the super-admin
        // "comprehensive user profile" screen has realistic timelines,
        // visit histories, and risk-flag triggers on a fresh seed.
        $this->call(UserActivityPatternsSeeder::class);

        // Team members + activity-log rows for a sample of clinics so
        // the new "فريقي" + "نشاط الفريق" screens have content on a
        // fresh seed. Lightweight & idempotent (skips if rows exist).
        $this->call(ClinicTeamSeeder::class);

        // CRM-shaped bookings data: assignees, VIP customers, cancel-
        // risk customers, tags, and activity-log rows so the new
        // Kanban "لوحة الحجوزات" has signals to render (VIP badges,
        // smart suggestions, Timeline entries). Depends on ClinicTeam-
        // Seeder (needs team members to assign to).
        $this->call(BookingsCrmSeeder::class);

        // Phase 1 foundation: walk every booking / complaint /
        // price-quote and link it to the unified Customer entity
        // (creating Customers along the way). Must run LAST — sees
        // the full data picture produced by every seeder above.
        $this->call(CustomersBackfillSeeder::class);
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
            // NOTE: basic/premium_subscription_price removed — package prices
            // now live solely in the subscription_packages catalogue (the
            // single source of truth). Nothing reads these keys anymore.
            ['key' => 'subscription_duration_days', 'value' => '90', 'type' => 'integer', 'group' => 'subscriptions', 'label' => 'مدة الاشتراك (أيام)'],
            ['key' => 'subscription_reminder_days', 'value' => '10', 'type' => 'integer', 'group' => 'subscriptions', 'label' => 'أيام التذكير قبل انتهاء الاشتراك'],
            ['key' => 'subscription_grace_period_days', 'value' => '7', 'type' => 'integer', 'group' => 'subscriptions', 'label' => 'مدة فترة السماح بعد الانتهاء (أيام)', 'description' => 'عدد الأيام بعد انتهاء الاشتراك التي يبقى فيها المجمع مرئياً قبل تعليقه تلقائياً. الافتراضي 7 أيام.'],
            ['key' => 'subscription_auto_suspend_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'subscriptions', 'label' => 'تفعيل التعليق التلقائي بعد مهلة السماح', 'description' => 'عند التفعيل، يُعلَّق المجمع تلقائياً (status=suspended) بعد انقضاء مهلة السماح. عند الإيقاف، يبقى نشطاً حتى يتدخل الأدمن يدوياً.'],
            ['key' => 'basic_articles_limit', 'value' => '5', 'type' => 'integer', 'group' => 'limits', 'label' => 'حد المقالات (أساسي/شهر)'],
            ['key' => 'otp_expiry_minutes', 'value' => '5', 'type' => 'integer', 'group' => 'auth', 'label' => 'مدة صلاحية OTP (دقائق)'],

            // ----- OTP delivery channels (SMS + WhatsApp) -----
            // The dispatcher tries the primary channel first, then the other
            // one when fallback is on — but only channels enabled here. WhatsApp
            // sender numbers live in the whatsapp_senders table (managed at
            // /app/admin/whatsapp-senders).
            ['key' => 'otp_whatsapp_enabled', 'value' => '1',        'type' => 'boolean', 'group' => 'auth', 'label' => 'تفعيل إرسال OTP عبر واتساب', 'description' => 'عند التفعيل، يُرسَل كود التحقق عبر واتساب باستخدام الأرقام المُضافة في صفحة "أرقام واتساب". يتطلب وجود رقم مرسِل واحد نشط على الأقل مع بيانات اعتماد صحيحة.'],
            ['key' => 'otp_sms_enabled',      'value' => '1',        'type' => 'boolean', 'group' => 'auth', 'label' => 'تفعيل إرسال OTP عبر SMS',     'description' => 'عند التفعيل، يُرسَل كود التحقق عبر الرسائل النصية (Unifonic).'],
            ['key' => 'otp_primary_channel',  'value' => 'whatsapp', 'type' => 'string',  'group' => 'auth', 'label' => 'القناة الأساسية لإرسال OTP',  'description' => 'القناة التي تُجرَّب أولاً. القيمة المسموحة: whatsapp أو sms. الافتراضي whatsapp.'],
            ['key' => 'otp_fallback_enabled', 'value' => '1',        'type' => 'boolean', 'group' => 'auth', 'label' => 'التحويل التلقائي للقناة الأخرى عند الفشل', 'description' => 'عند التفعيل، إذا فشلت القناة الأساسية يُعاد الإرسال عبر القناة الأخرى (إن كانت مُفعّلة). عند الإيقاف، تُستخدم القناة الأساسية فقط.'],
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

            // ── AI Center additions: admin-controlled tone + free-text
            // augmentation + emergency response template. All three are
            // singletons; richer per-rule strings live in ai_restrictions.
            ['key' => 'ai_tone',                       'value' => 'friendly', 'type' => 'string', 'group' => 'ai', 'label' => 'نبرة المساعد',                'description' => 'النبرة الافتراضية لردود المساعد: formal (رسمية), friendly (ودودة), concise (مختصرة).'],
            ['key' => 'ai_system_prompt_extension',    'value' => null,       'type' => 'text',   'group' => 'ai', 'label' => 'إضافة لبرومت النظام',           'description' => 'نص يُضاف إلى برومت النظام في كل محادثة (لا يستبدل البرومت الأصلي). مكان مناسب لإضافات صغيرة دون تعديل ai_system_prompt كاملاً.'],
            ['key' => 'ai_emergency_response_template','value' => null,       'type' => 'text',   'group' => 'ai', 'label' => 'قالب رد الطوارئ',               'description' => 'الرد الذي يَظهر فوراً عند تطابق كلمة طوارئ مُضافة من الأدمن. استخدم :ambulance ليُستبدَل برقم الإسعاف. اتركه فارغاً لاستخدام الافتراضي.'],

            // ── Phase 2 expansion: knobs that used to be hardcoded
            // constants in service classes are now editable here. Empty
            // / 0 / null values fall back to the original code defaults.
            ['key' => 'ai_default_locale',                 'value' => 'ar',  'type' => 'string',  'group' => 'ai', 'label' => 'لغة المساعد الافتراضية', 'description' => 'الافتراضي عند عدم تمرير لغة من العميل. ar أو en.'],
            ['key' => 'ai_max_query_length',               'value' => '500', 'type' => 'integer', 'group' => 'ai', 'label' => 'أقصى طول للسؤال (حروف)', 'description' => 'الحدّ الأقصى لطول رسالة المستخدم في كل turn. الافتراضي 500.'],
            ['key' => 'ai_conversation_window_minutes',    'value' => '30',  'type' => 'integer', 'group' => 'ai_advanced', 'label' => 'نافذة تجميع المحادثات (دقائق)', 'description' => 'إذا تَكلّم نفس المستخدم خلال هذه الفترة من آخر turn، يُعتبر استكمالاً للمحادثة نفسها. الافتراضي 30.'],
            ['key' => 'ai_summarize_use_llm',              'value' => '0',   'type' => 'boolean', 'group' => 'ai_advanced', 'label' => 'استخدام LLM لتوليد ملخّص اهتمامات المستخدم', 'description' => 'عند التفعيل، الـbackground job يستدعي الـLLM لإنتاج ملخّص أغنى. عند الإيقاف، يَستخدم القاعدة heuristic فقط (افتراضي — أرخص).'],
            ['key' => 'ai_pii_masking_enabled',            'value' => '1',   'type' => 'boolean', 'group' => 'ai_advanced', 'label' => 'إخفاء البيانات الشخصية افتراضياً', 'description' => 'عند التفعيل، سجل المحادثات يَعرض الأسماء والهواتف مخفية افتراضياً (مع زر كشف). عند الإيقاف، يَظهر النص الخام مباشرة.'],
            ['key' => 'ai_logs_retention_days',            'value' => '0',   'type' => 'integer', 'group' => 'ai_advanced', 'label' => 'مدة الاحتفاظ بسجلات المحادثات (أيام)', 'description' => '0 = للأبد. أي رقم > 0 = يَحذف السجلات الأقدم منه عند تشغيل php artisan ai:prune-logs (يُجدول عادةً يومياً).'],

            // ── Alert thresholds — widget on the admin dashboard.
            ['key' => 'ai_alert_emergency_window_hours',   'value' => '24',  'type' => 'integer', 'group' => 'ai_alerts', 'label' => 'نافذة إنذار الطوارئ (ساعات)',  'description' => 'إذا حصلت محادثة طوارئ خلال هذه الفترة، يَظهر إنذار في widget لوحة المعلومات. الافتراضي 24.'],
            ['key' => 'ai_block_spike_threshold',          'value' => '3',   'type' => 'float',   'group' => 'ai_alerts', 'label' => 'حد ارتفاع معدّل الرفض (مضاعفات)', 'description' => 'إنذار "ارتفاع غير طبيعي في الرفض" يَظهر عندما معدّل اليوم >= هذه المضاعفة × معدّل 30 يوم. الافتراضي 3.0.'],
            ['key' => 'ai_block_spike_min_volume',         'value' => '20',  'type' => 'integer', 'group' => 'ai_alerts', 'label' => 'حد أدنى للحجم قبل تشغيل إنذار الرفض', 'description' => 'الإنذار لا يَفعل إلا إذا حصل اليوم >= هذا العدد من المحادثات (لتجنّب false positives على عيّنات صغيرة). الافتراضي 20.'],

            // ── Cache TTLs — performance knobs.
            ['key' => 'ai_analytics_cache_ttl',            'value' => '300', 'type' => 'integer', 'group' => 'ai_cache', 'label' => 'مدة Cache الإحصائيات (ثواني)',   'description' => 'كم ثانية تَبقى نتائج تبويب الإحصائيات في الذاكرة. زِد الرقم لتقليل الحمل، قلّله للحصول على أرقام أحدث. الافتراضي 300.'],
            ['key' => 'ai_widget_cache_ttl',               'value' => '60',  'type' => 'integer', 'group' => 'ai_cache', 'label' => 'مدة Cache widget لوحة المعلومات',  'description' => 'كم ثانية تَبقى أرقام الـwidget في الذاكرة. الافتراضي 60.'],
            ['key' => 'ai_restrictions_cache_ttl',         'value' => '60',  'type' => 'integer', 'group' => 'ai_cache', 'label' => 'مدة Cache الموانع',              'description' => 'كم ثانية تَبقى قائمة الموانع النشطة في الذاكرة. الإضافة/التعديل يَبطل الـcache تلقائياً. الافتراضي 60.'],

            // ── Heuristic seriousness keywords used by SummarizeJob to
            // bucket each conversation. JSON arrays. Admin can extend.
            ['key' => 'ai_seriousness_keywords_near_decision', 'value' => '["احجز","أبي احجز","أريد حجز","أبدأ العلاج","تواصل معي","احجز موعد"]', 'type' => 'json', 'group' => 'ai_advanced', 'label' => 'كلمات "قريب من قرار"', 'description' => 'قائمة كلمات تَدل أن المستخدم قريب من حجز/قرار. تَنعكس على مستوى الجدية في ملخّص اهتمامات المستخدم.'],
            ['key' => 'ai_seriousness_keywords_comparing',     'value' => '["أيهما","مقارنة","الفرق بين","أرخص","أفضل","كم سعر","بكم"]',          'type' => 'json', 'group' => 'ai_advanced', 'label' => 'كلمات "يقارن"',         'description' => 'كلمات تَدل أن المستخدم في مرحلة مقارنة بين خيارات.'],
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
     * Register the primary WhatsApp sender number. The Wappi profile_id +
     * token are secrets the super-admin adds from the panel, so we only seed
     * the number itself (idempotent — never clobbers credentials on re-run).
     */
    private function seedWhatsAppSenders(): void
    {
        \App\Models\WhatsAppSender::firstOrCreate(
            ['phone' => '966564844382'],
            [
                'label'     => 'الرقم الرئيسي',
                'provider'  => 'wappi',
                'is_active' => true,
                'priority'  => 0,
            ],
        );
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
