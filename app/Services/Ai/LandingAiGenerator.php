<?php

namespace App\Services\Ai;

use App\Models\Category;
use App\Models\City;
use App\Models\Clinic;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Generates draft landing-page copy (title, description, FAQ, SEO) from the
 * platform's own data. Provider-agnostic via AiProviderFactory (Gemini /
 * OpenAI / Anthropic), mirroring AiContentService's prompt → complete →
 * extract-first-JSON pattern. The result is ALWAYS a draft for admin review —
 * nothing is published automatically.
 */
class LandingAiGenerator
{
    public function __construct(private readonly AiProviderFactory $factory) {}

    public function isConfigured(): bool
    {
        return $this->factory->isConfigured();
    }

    /**
     * @return array{title:string, description:string, faq:array<int,array{q:string,a:string}>,
     *               seo_title:string, seo_description:string, keywords:string, cta:string}
     */
    public function generate(?Clinic $clinic, ?string $service, ?City $city, ?Category $category): array
    {
        $clinicName = $clinic?->name ?? '—';
        $cityName   = $city?->name ?? $clinic?->city?->name ?? '—';
        $specialty  = $category?->name ?? '—';
        $service    = $service ?: '—';

        $prompt = <<<PROMPT
أنت خبير تسويق رقمي للقطاع الطبي. أنشئ محتوى صفحة هبوط (Landing Page) باللغة العربية الفصحى
لجذب عملاء جدد، اعتماداً على البيانات التالية من داخل المنصة:

المجمع الطبي: {$clinicName}
المدينة: {$cityName}
التخصص: {$specialty}
الخدمة المستهدفة: {$service}

قواعد صارمة:
- لا تقدّم أي وعد علاجي مبالغ فيه أو ضمان نتائج.
- أسلوب احترافي وجذّاب يدفع للحجز.
- لا تستخدم Markdown.
- العنوان قصير ومؤثر (لا يتجاوز 10 كلمات).
- الوصف فقرة واحدة (40-70 كلمة).
- أنشئ 4 أسئلة شائعة بإجابات مختصرة مفيدة.
- seo_title بحد أقصى 60 حرفاً، seo_description بحد أقصى 155 حرفاً.
- keywords: كلمات مفتاحية مفصولة بفواصل.
- cta: نص زر إجراء قصير (مثل: احجز موعدك الآن).

أعد إجابتك بصيغة JSON صالحة فقط (بدون أي نص خارج JSON) بهذا الشكل بالضبط:
{
  "title": "...",
  "description": "...",
  "faq": [{"q": "...", "a": "..."}],
  "seo_title": "...",
  "seo_description": "...",
  "keywords": "...",
  "cta": "..."
}
PROMPT;

        $raw = $this->factory->make()->complete($prompt, maxTokens: 1500, temperature: 0.4);

        if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $raw, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return [
                    'title'           => (string) ($decoded['title'] ?? ''),
                    'description'     => (string) ($decoded['description'] ?? ''),
                    'faq'             => $this->normalizeFaq($decoded['faq'] ?? []),
                    'seo_title'       => (string) ($decoded['seo_title'] ?? ''),
                    'seo_description' => (string) ($decoded['seo_description'] ?? ''),
                    'keywords'        => (string) ($decoded['keywords'] ?? ''),
                    'cta'             => (string) ($decoded['cta'] ?? ''),
                ];
            }
        }

        Log::warning('AI returned non-JSON for landing page generation', ['raw' => $raw]);
        throw new RuntimeException('AI returned invalid JSON: ' . substr($raw, 0, 200));
    }

    private function normalizeFaq(mixed $faq): array
    {
        if (! is_array($faq)) {
            return [];
        }

        return collect($faq)
            ->filter(fn ($i) => is_array($i) && filled($i['q'] ?? null) && filled($i['a'] ?? null))
            ->map(fn ($i) => ['q' => (string) $i['q'], 'a' => (string) $i['a']])
            ->values()
            ->all();
    }
}
