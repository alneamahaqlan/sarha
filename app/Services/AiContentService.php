<?php

namespace App\Services;

use App\Services\Ai\AiProviderFactory;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * High-level AI tasks the clinic + admin panels rely on:
 *   - generateExcerpt / generateArticle  → Tiptap "AI hint" buttons
 *   - analyzeExcelColumns                → Service-import column mapping
 *
 * Provider-agnostic — picks Gemini / OpenAI / Anthropic via AiProviderFactory
 * which reads /admin/system-settings (group=ai). Replaces the older
 * AnthropicService that hard-coded Claude.
 */
class AiContentService
{
    public function __construct(private readonly AiProviderFactory $factory) {}

    public function isConfigured(): bool
    {
        return $this->factory->isConfigured();
    }

    /** Diagnostic helper for logs / debug pages. */
    public function activeProvider(): string
    {
        return $this->factory->activeProviderName();
    }

    /**
     * 40-60 word Arabic article excerpt.
     */
    public function generateExcerpt(string $articleTitle, string $clinicName, string $specialty = ''): string
    {
        $prompt = <<<PROMPT
أنت كاتب محتوى طبي. اكتب مقدمة قصيرة من 40-60 كلمة باللغة العربية الفصحى لمقال طبي بعنوان:

"{$articleTitle}"

المجمع: {$clinicName}
التخصص: {$specialty}

قواعد صارمة:
- تجنّب أي وعد علاجي مبالغ فيه.
- اكتب أسلوباً مهنياً ودوداً.
- لا تستخدم Markdown أو أي تنسيق.
- ابدأ مباشرة بدون مقدمات مثل "إليك" أو "هذه".
PROMPT;

        return $this->factory->make()->complete($prompt, maxTokens: 300);
    }

    /**
     * 300-400 word Arabic article body — no Markdown, prose paragraphs.
     */
    public function generateArticle(string $articleTitle, string $clinicName, string $specialty = ''): string
    {
        $prompt = <<<PROMPT
أنت كاتب محتوى طبي محترف. اكتب مقالاً كاملاً من 300-400 كلمة باللغة العربية الفصحى لمجمع طبي.

العنوان: "{$articleTitle}"
المجمع: {$clinicName}
التخصص: {$specialty}

قواعد صارمة:
- لا تقدم تشخيصاً أو علاجاً محدداً، فقط معلومات عامة.
- لا تستخدم Markdown أو رؤوس أو قوائم بأرقام.
- اكتب فقرات نثرية طبيعية مفصولة بسطر فارغ.
- اختم بدعوة لطيفة للتواصل مع المجمع.
- لا تكرر العنوان في بداية المقال.
PROMPT;

        return $this->factory->make()->complete($prompt, maxTokens: 1500);
    }

    /**
     * Map user-supplied Excel column headers to platform service fields.
     * Returns matches with confidence scores.
     *
     * @param array<string> $columns
     * @param array<array<string,mixed>> $sampleRows (max 3 rows are forwarded)
     * @return array<array{column: string, mapped_to: ?string, confidence: int, reason: string}>
     */
    public function analyzeExcelColumns(array $columns, array $sampleRows = []): array
    {
        $fields = [
            'name'             => 'اسم الخدمة',
            'price'            => 'السعر الحالي بالريال',
            'description'      => 'وصف الخدمة',
            'duration_minutes' => 'مدة الخدمة بالدقائق',
            'category'         => 'التصنيف أو التخصص',
        ];

        $columnsJson = json_encode($columns, JSON_UNESCAPED_UNICODE);
        $sampleJson  = json_encode(array_slice($sampleRows, 0, 3), JSON_UNESCAPED_UNICODE);
        $fieldsJson  = json_encode($fields, JSON_UNESCAPED_UNICODE);

        $prompt = <<<PROMPT
أنت محرر بيانات. ستحلل أعمدة جدول Excel وتربطها بحقول النظام التالية:

الحقول المتاحة في النظام:
{$fieldsJson}

أعمدة المستخدم:
{$columnsJson}

عينة من البيانات (لمساعدتك في الفهم):
{$sampleJson}

أعد إجابتك بصيغة JSON صالحة فقط (بدون أي شرح خارج JSON) بهذا الشكل بالضبط:
[
  {"column": "<اسم العمود الأصلي>", "mapped_to": "<اسم الحقل في النظام أو null>", "confidence": <0-100>, "reason": "<شرح قصير>"}
]

اربط فقط الأعمدة المتطابقة فعلاً. إذا لم يطابق العمود أي حقل، اجعل mapped_to = null.
PROMPT;

        $raw = $this->factory->make()->complete($prompt, maxTokens: 2000);

        // Models sometimes wrap JSON in ```json fences — match the first array.
        if (preg_match('/\[(?:[^\[\]]|(?R))*\]/s', $raw, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        Log::warning('AI returned non-JSON for Excel mapping', ['raw' => $raw]);
        throw new RuntimeException('AI returned invalid JSON: ' . substr($raw, 0, 200));
    }

    /**
     * Extract a clinic catalog (services + promotional offers) from free-form
     * text — a pasted price list, a .txt export, ad copy, etc. Unlike
     * analyzeExcelColumns this makes no assumption about tabular structure.
     *
     * The model also gets the clinic's specialty list and existing services so
     * it can SUGGEST a category for each service and SUGGEST which existing
     * service an offer applies to. All suggested ids are validated by the
     * caller — we never trust the model to invent a real foreign key.
     *
     * @param array<int,array{id:int,name:string}> $categories       Active specialties.
     * @param array<int,array{id:int,name:string}> $existingServices Clinic's current services.
     * @return array{services: array<int,array{name:string,description:?string,price:mixed,category_ids:array<int>}>, offers: array<int,array{title:string,description:?string,old_price:mixed,price:mixed,service_id:?int}>}
     */
    public function extractCatalogFromText(string $text, array $categories = [], array $existingServices = []): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['services' => [], 'offers' => []];
        }

        // Cap very large pastes to protect the input token budget.
        $text = mb_substr($text, 0, 12000);

        $categoriesJson = json_encode($categories, JSON_UNESCAPED_UNICODE);
        $servicesJson   = json_encode($existingServices, JSON_UNESCAPED_UNICODE);

        $prompt = <<<PROMPT
أنت محرر بيانات لعيادة طبية. سأعطيك نصاً حراً (قائمة أسعار أو وصف خدمات وعروض) وعليك استخراج عنصرين منه: الخدمات والعروض.

التمييز بين الخدمة والعرض:
- "خدمة" = بند عادي له سعر واحد (مثل: تنظيف الأسنان 150).
- "عرض" = ترويج فيه خصم أو سعر قبل وبعد أو باقة أو كلمة (عرض/خصم/باقة) (مثل: تبييض 800 بدلاً من 1200).

التخصصات المتاحة (اقترح المناسب لكل خدمة من هذه القائمة فقط عبر id):
{$categoriesJson}

خدمات العيادة الحالية (إذا كان العرض يخص إحداها اقترح service_id منها، وإلا اجعله null):
{$servicesJson}

النص:
\"\"\"
{$text}
\"\"\"

أعد إجابتك بصيغة JSON صالحة فقط (بدون أي شرح أو نص خارج JSON) بهذا الشكل بالضبط:
{
  "services": [
    {"name": "<اسم الخدمة>", "description": "<وصف مختصر أو null>", "price": <رقم أو null>, "category_ids": [<id أو لا شيء>]}
  ],
  "offers": [
    {"title": "<عنوان العرض>", "description": "<وصف مختصر أو null>", "old_price": <رقم أو null>, "price": <رقم أو null>, "service_id": <id أو null>}
  ]
}

قواعد صارمة:
- "name" و "title" إجباريان ومختصران (لا تضع السعر داخل الاسم).
- الأسعار أرقام فقط بالريال بدون عملة أو رموز، أو null.
- "category_ids" من قائمة التخصصات المتاحة فقط (يمكن أن تكون فارغة []).
- "service_id" من خدمات العيادة الحالية فقط أو null.
- في العرض "old_price" السعر قبل الخصم و "price" بعده.
- لا تخترع عناصر غير موجودة في النص.
PROMPT;

        $raw = $this->factory->make()->complete($prompt, maxTokens: 4000, temperature: 0.0);

        // Models sometimes wrap JSON in ```json fences — match the first object.
        if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $raw, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return [
                    'services' => is_array($decoded['services'] ?? null) ? $decoded['services'] : [],
                    'offers'   => is_array($decoded['offers'] ?? null) ? $decoded['offers'] : [],
                ];
            }
        }

        Log::warning('AI returned non-JSON for catalog extraction', ['raw' => $raw]);
        throw new RuntimeException('AI returned invalid JSON: ' . substr($raw, 0, 200));
    }
}
