<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

/**
 * One-shot seeder to insert the 14 expanded AI settings from Phase 3.
 * Idempotent — `updateOrCreate` on key. Safe to re-run.
 */
class AiSettingsExpansionSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['ai_default_locale',                          'ar',  'string',  'ai',          'لغة المساعد الافتراضية',                'ar أو en. الافتراضي عند عدم تمرير لغة من العميل.'],
            ['ai_max_query_length',                        '500', 'integer', 'ai',          'أقصى طول للسؤال (حروف)',                 'الحدّ الأقصى لطول رسالة المستخدم في كل turn.'],
            ['ai_conversation_window_minutes',             '30',  'integer', 'ai_advanced', 'نافذة تجميع المحادثات (دقائق)',          'إذا تَكلّم نفس المستخدم خلال هذه الفترة، يُعتبر نفس الـthread.'],
            ['ai_summarize_use_llm',                       '0',   'boolean', 'ai_advanced', 'استخدام LLM لتوليد ملخّص اهتمامات',        'مكلف. عند الإيقاف، يَستخدم heuristic فقط (افتراضي).'],
            ['ai_pii_masking_enabled',                     '1',   'boolean', 'ai_advanced', 'إخفاء البيانات الشخصية افتراضياً',        'سجل المحادثات يَعرض الأسماء والهواتف مخفية مع زر كشف.'],
            ['ai_logs_retention_days',                     '0',   'integer', 'ai_advanced', 'مدة الاحتفاظ بسجلات المحادثات (أيام)',    '0 = للأبد. أي > 0 = يَحذف الأقدم عند تشغيل ai:prune-logs.'],
            ['ai_alert_emergency_window_hours',            '24',  'integer', 'ai_alerts',   'نافذة إنذار الطوارئ (ساعات)',           'إنذار widget لوحة المعلومات.'],
            ['ai_block_spike_threshold',                   '3',   'float',   'ai_alerts',   'حد ارتفاع معدّل الرفض (مضاعفات)',         'إنذار يَظهر عند معدّل اليوم >= هذه × معدّل 30 يوم.'],
            ['ai_block_spike_min_volume',                  '20',  'integer', 'ai_alerts',   'حد أدنى للحجم قبل تشغيل الإنذار',         'لتجنّب false positives على عيّنات صغيرة.'],
            ['ai_analytics_cache_ttl',                     '300', 'integer', 'ai_cache',    'مدة Cache الإحصائيات (ثواني)',           'زِد للحمل أقل، قلّل لأرقام أحدث.'],
            ['ai_widget_cache_ttl',                        '60',  'integer', 'ai_cache',    'مدة Cache widget لوحة المعلومات',        'ثواني.'],
            ['ai_restrictions_cache_ttl',                  '60',  'integer', 'ai_cache',    'مدة Cache الموانع',                     'الإضافة/التعديل تَبطل الـcache تلقائياً.'],
            ['ai_seriousness_keywords_near_decision',
                json_encode(['احجز','أبي احجز','أريد حجز','أبدأ العلاج','تواصل معي','احجز موعد'], JSON_UNESCAPED_UNICODE),
                'json', 'ai_advanced', 'كلمات "قريب من قرار"',
                'تَنعكس على مستوى الجدية في ملخّص اهتمامات المستخدم.'],
            ['ai_seriousness_keywords_comparing',
                json_encode(['أيهما','مقارنة','الفرق بين','أرخص','أفضل','كم سعر','بكم'], JSON_UNESCAPED_UNICODE),
                'json', 'ai_advanced', 'كلمات "يقارن"',
                'كلمات تَدل أن المستخدم في مرحلة مقارنة بين خيارات.'],
        ];

        foreach ($rows as [$key, $value, $type, $group, $label, $description]) {
            SystemSetting::updateOrCreate(
                ['key' => $key],
                compact('key', 'value', 'type', 'group', 'label', 'description'),
            );
        }

        $this->command?->info('AiSettingsExpansionSeeder: ' . count($rows) . ' settings upserted.');
    }
}
