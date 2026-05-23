<?php

namespace App\Services;

use App\Models\Category;
use App\Models\City;
use App\Models\Clinic;
use App\Services\Ai\AiProviderFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Public AI assistant — matches Arabic/English search queries to clinics.
 *
 * Clinic *selection* stays deterministic (DB query, no hallucinations).
 * Reply *text* is upgraded by the active LLM provider (Gemini default,
 * OpenAI or Anthropic per super-admin choice) when configured; otherwise
 * we fall back to translated string templates.
 *
 * Always safety-rejects medical-advice questions.
 */
class AiAssistantService
{
    /** Medical question keywords — these get redirected to "see a doctor". */
    private const MEDICAL_KEYWORDS_AR = [
        'وش أسوي', 'وش اسوي', 'كيف أعالج', 'كيف اعالج', 'ما العلاج',
        'ما الدواء', 'أعراض', 'تشخيص', 'هل أنا مصاب', 'هل اصاب', 'هل عندي',
        'متى أراجع', 'وصفة', 'حبوب', 'دواء', 'كم جرعة',
    ];
    private const MEDICAL_KEYWORDS_EN = [
        'how do i treat', 'what should i take', 'do i have', 'am i sick',
        'symptoms', 'diagnose', 'prescribe', 'dosage', 'cure',
    ];

    private const QUICK_PROMPTS = [
        'find_cheap_dental'  => 'site.ai_qp_cheap_dental',
        'best_dermatology'   => 'site.ai_qp_best_dermatology',
        'nearby_pediatric'   => 'site.ai_qp_pediatric_near',
        'compare_two'        => 'site.ai_qp_compare',
    ];

    public function __construct(private readonly AiProviderFactory $providers) {}

    public function quickPrompts(): array
    {
        return self::QUICK_PROMPTS;
    }

    /**
     * Main entry. Returns array { reply: string, clinics: Collection, kind: string, provider: ?string }.
     */
    public function ask(string $query, ?int $cityId = null): array
    {
        $query = trim($query);
        if ($query === '') {
            return $this->wrap('empty', __('site.ai_empty'), collect());
        }

        // 1) Medical safety filter — always handled locally, never sent to LLM.
        if ($this->looksMedical($query)) {
            return $this->wrap(
                'rejected',
                __('site.ai_medical_disclaimer'),
                $this->matchByKeyword($query, $cityId),
            );
        }

        // 2) Local keyword match (deterministic — LLM never picks clinics).
        $clinics = $this->matchByKeyword($query, $cityId);

        if ($clinics->isEmpty()) {
            return $this->wrap('no_match', __('site.ai_no_match'), collect());
        }

        // 3) Reply text — LLM if configured, template otherwise.
        $reply = $this->generateReply($query, $clinics)
            ?? __('site.ai_found_matches', ['count' => $clinics->count()]);

        return $this->wrap('matched', $reply, $clinics);
    }

    /**
     * Ask the active LLM to write a one-paragraph friendly Arabic reply
     * describing the matched clinics. Returns null on any error so the
     * caller falls back to the templated reply — chat must never fail
     * just because the AI provider is down.
     */
    private function generateReply(string $query, Collection $clinics): ?string
    {
        if (! $this->providers->isConfigured()) {
            return null;
        }

        $names = $clinics->map(fn (Clinic $c) => trim(sprintf(
            '- %s (%s)',
            $c->name,
            optional($c->city)->name ?? '—',
        )))->implode("\n");

        $prompt = <<<PROMPT
أنت مساعد ذكي لمنصة "سعرها" لحجز الخدمات الطبية في السعودية.
المستخدم سأل: "{$query}"
ووجدنا له هذه المجمعات المطابقة:
{$names}

اكتب رداً قصيراً جداً باللغة العربية (سطرين كحد أقصى، 30-50 كلمة) يلخّص النتائج بأسلوب ودود ومحترف.
قواعد صارمة:
- لا تذكر أسعاراً أو أرقاماً لم تُعطَ لك.
- لا تقدم أي نصيحة طبية.
- لا تستخدم Markdown أو رؤوس أو قوائم.
- اختم بدعوة لطيفة للضغط على المجمع لعرض الخدمات.
PROMPT;

        try {
            return $this->providers->make()->complete($prompt, maxTokens: 200);
        } catch (Throwable $e) {
            Log::warning('AiAssistant LLM reply failed — falling back to template', [
                'provider' => $this->providers->activeProviderName(),
                'error'    => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function wrap(string $kind, string $reply, Collection $clinics): array
    {
        return [
            'kind'     => $kind,
            'reply'    => $reply,
            'clinics'  => $clinics,
            'provider' => $this->providers->isConfigured() ? $this->providers->activeProviderName() : null,
        ];
    }

    private function looksMedical(string $query): bool
    {
        $lower = mb_strtolower($query);
        foreach (self::MEDICAL_KEYWORDS_AR as $needle) {
            if (str_contains($query, $needle)) return true;
        }
        foreach (self::MEDICAL_KEYWORDS_EN as $needle) {
            if (str_contains($lower, $needle)) return true;
        }
        return false;
    }

    private function matchByKeyword(string $query, ?int $cityId): Collection
    {
        $base = Clinic::publiclyVisible()->with(['city', 'categories']);

        // City detection
        if ($cityId) {
            $base->where('city_id', $cityId);
        } else {
            $city = City::query()
                ->where(fn($q) => $q->where('name', 'like', "%{$query}%")
                    ->orWhere('name_en', 'like', "%{$query}%"))
                ->first();
            if ($city) $base->where('city_id', $city->id);
        }

        // Category detection
        $category = Category::query()
            ->where(fn($q) => $q->where('name', 'like', "%{$query}%")
                ->orWhere('name_en', 'like', "%{$query}%"))
            ->first();
        if ($category) {
            $base->whereHas('categories', fn($q) => $q->where('categories.id', $category->id));
        }

        // Price keywords
        $cheap = preg_match('/(رخيص|أرخص|أوفر|أقل سعر|cheap|cheapest|affordable)/iu', $query);
        $best  = preg_match('/(أفضل|الأحسن|أعلى تقييم|best|top)/iu', $query);

        if ($cheap) {
            $base->withMin(['services as min_price' => fn($q) => $q->where('is_active', true)->whereNotNull('price')], 'price')
                ->orderBy('min_price');
        } elseif ($best) {
            $base->withAvg('googleReviews', 'rating')
                ->orderByDesc('google_reviews_avg_rating');
        } else {
            $base->rankedForListing();
        }

        // Free-text fallback
        if (! $category && ! $cityId) {
            $base->where(fn($q) => $q
                ->where('name', 'like', "%{$query}%")
                ->orWhere('description', 'like', "%{$query}%")
                ->orWhereHas('categories', fn($c) => $c
                    ->where('name', 'like', "%{$query}%")
                    ->orWhere('name_en', 'like', "%{$query}%")
                )
            );
        }

        return $base->take(3)->get();
    }
}
