<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AnthropicService
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly string $model = 'claude-sonnet-4-5',
    ) {}

    public function isConfigured(): bool
    {
        return (bool) ($this->apiKey ?? config('services.anthropic.key'));
    }

    /**
     * Generate ~50-word excerpt for an article about a service.
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

        return $this->complete($prompt, maxTokens: 200);
    }

    /**
     * Generate full 300-400 word article.
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

        return $this->complete($prompt, maxTokens: 1500);
    }

    /**
     * Match user-supplied Excel column headers to platform service fields.
     * Returns matches with confidence scores.
     *
     * @param array<string> $columns
     * @param array<array<string,mixed>> $sampleRows (max 3 rows)
     * @return array<array{column: string, mapped_to: ?string, confidence: int, reason: string}>
     */
    public function analyzeExcelColumns(array $columns, array $sampleRows = []): array
    {
        $fields = [
            'name'             => 'اسم الخدمة',
            'price'            => 'السعر الحالي بالريال',
            'old_price'        => 'السعر القديم/قبل الخصم بالريال',
            'description'      => 'وصف الخدمة',
            'duration_minutes' => 'مدة الخدمة بالدقائق',
            'category'         => 'التصنيف أو التخصص',
        ];

        $columnsJson = json_encode($columns, JSON_UNESCAPED_UNICODE);
        $sampleJson = json_encode(array_slice($sampleRows, 0, 3), JSON_UNESCAPED_UNICODE);
        $fieldsJson = json_encode($fields, JSON_UNESCAPED_UNICODE);

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

        $raw = $this->complete($prompt, maxTokens: 2000);

        // Extract JSON array (model may wrap it in ```json blocks)
        if (preg_match('/\[(?:[^\[\]]|(?R))*\]/s', $raw, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new RuntimeException('AI returned invalid JSON: ' . substr($raw, 0, 200));
    }

    private function complete(string $prompt, int $maxTokens = 1024): string
    {
        $key = $this->apiKey ?? config('services.anthropic.key');
        if (! $key) {
            throw new RuntimeException('ANTHROPIC_API_KEY is not configured.');
        }

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'x-api-key'         => $key,
                    'anthropic-version' => self::API_VERSION,
                    'content-type'      => 'application/json',
                ])
                ->post(self::ENDPOINT, [
                    'model'      => $this->model ?: config('services.anthropic.model'),
                    'max_tokens' => $maxTokens,
                    'messages'   => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::error('Anthropic request failed', ['error' => $e->getMessage()]);
            throw new RuntimeException('AI request failed: ' . $e->getMessage());
        }

        if (! $response->successful()) {
            throw new RuntimeException('AI HTTP ' . $response->status() . ': ' . $response->body());
        }

        $body = $response->json();
        $text = $body['content'][0]['text'] ?? null;

        if (! $text) {
            throw new RuntimeException('AI returned empty response: ' . json_encode($body));
        }

        return trim($text);
    }
}
