<?php

namespace App\Services;

use App\Models\Category;
use App\Models\City;
use App\Models\Clinic;
use App\Models\SystemSetting;
use App\Services\Ai\AiProviderFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Public AI assistant — full conversational chat for the Saerha platform.
 *
 * Architecture
 * ------------
 *   1. Hard safety filter (medical advice) — runs locally, NEVER sent to LLM.
 *   2. Local clinic search — deterministic Eloquent query that finds up to
 *      three matching clinics. The LLM never picks clinics; it only sees
 *      them as context, so hallucinated clinic names are impossible.
 *   3. LLM turn — the active provider (Gemini default) receives:
 *        - the admin-configurable system prompt + restrictions
 *        - the matched clinics as a structured "context" block
 *        - the user query
 *      and produces a natural, on-tone reply.
 *   4. Fallback — when the LLM is disabled or unreachable we fall back to
 *      the translated template strings so the chat keeps working.
 *
 * All knobs live in /app/admin/system-settings (group=ai):
 *   ai_freeform_enabled, ai_system_prompt, ai_restrictions,
 *   ai_max_tokens, ai_temperature, ai_assistant_name.
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

    /**
     * Stop-words stripped from the query before tokenized matching. Includes
     * Arabic interrogatives + prepositions + clinic-type nouns so a question
     * like "ابحث عن مجمع أسنان رخيص في الرياض" collapses to the meaningful
     * tokens ['أسنان', 'الرياض'].
     */
    private const STOPWORDS = [
        // Arabic verbs + prepositions + connectors
        'ابحث', 'أبحث', 'ابغى', 'أبغى', 'ابي', 'أبي', 'اريد', 'أريد', 'ودي', 'بدي',
        'عن', 'في', 'لي', 'مع', 'من', 'الى', 'إلى', 'على', 'هل', 'يوجد', 'فيه', 'وين', 'أين',
        'كيف', 'ايش', 'إيش', 'ماهي', 'ما', 'هو', 'هي',
        // Clinic-type nouns (matching these gives no signal — every clinic is one)
        'مجمع', 'مجمعات', 'مركز', 'مراكز', 'مستوصف', 'عيادة', 'عيادات', 'مستشفى',
        // Price/quality intent words (handled by the dedicated regex, not by token search)
        'رخيص', 'أرخص', 'ارخص', 'أوفر', 'اوفر', 'أفضل', 'افضل', 'الأحسن', 'الاحسن', 'سعر', 'أسعار', 'اسعار',
        // English equivalents
        'find', 'me', 'a', 'an', 'the', 'in', 'at', 'for', 'show', 'looking', 'cheap',
        'cheapest', 'affordable', 'best', 'top', 'where', 'is', 'are', 'price', 'prices',
        'clinic', 'clinics', 'center', 'centers',
    ];

    public function __construct(private readonly AiProviderFactory $providers) {}

    public function quickPrompts(): array
    {
        return self::QUICK_PROMPTS;
    }

    /**
     * Main entry. Returns array { kind, reply, clinics, provider, assistant_name }.
     *
     *   kind = 'empty' | 'rejected' | 'matched' | 'freeform' | 'no_match'
     */
    public function ask(string $query, ?int $cityId = null): array
    {
        $query = trim($query);
        if ($query === '') {
            return $this->wrap('empty', __('site.ai_empty'), collect());
        }

        // 1) Hard safety filter — never delegate medical questions to the LLM.
        if ($this->looksMedical($query)) {
            return $this->wrap(
                'rejected',
                __('site.ai_medical_disclaimer'),
                $this->matchByKeyword($query, $cityId),
            );
        }

        // 2) Local clinic search — deterministic context for the LLM.
        $clinics = $this->matchByKeyword($query, $cityId);

        // 3) Free-form conversational reply via the active LLM.
        if ($this->providers->isConfigured() && $this->freeformEnabled()) {
            $reply = $this->llmReply($query, $clinics);
            if ($reply !== null) {
                $kind = $clinics->isNotEmpty() ? 'matched' : 'freeform';
                return $this->wrap($kind, $reply, $clinics);
            }
        }

        // 4) Legacy template fallback (no LLM configured / failed).
        if ($clinics->isEmpty()) {
            return $this->wrap('no_match', __('site.ai_no_match'), collect());
        }
        return $this->wrap(
            'matched',
            __('site.ai_found_matches', ['count' => $clinics->count()]),
            $clinics,
        );
    }

    // ============================================================
    //   LLM call
    // ============================================================

    /**
     * Compose the prompt, call the active provider, return the reply or
     * null on any failure. The chat must never go down because the LLM is
     * down — the caller falls back to templates.
     */
    private function llmReply(string $query, Collection $clinics): ?string
    {
        try {
            $systemPrompt = $this->buildSystemPrompt();
            $userPrompt   = $this->buildUserPrompt($query, $clinics);

            $maxTokens   = (int)   ($this->setting('ai_max_tokens', 800));
            $temperature = (float) ($this->setting('ai_temperature', 0.5));

            return $this->providers->make()->complete(
                userPrompt: $userPrompt,
                maxTokens: max(64, $maxTokens),
                temperature: max(0.0, min(1.0, $temperature)),
                systemPrompt: $systemPrompt,
            );
        } catch (Throwable $e) {
            Log::warning('AiAssistant LLM call failed — falling back to template', [
                'provider' => $this->providers->activeProviderName(),
                'error'    => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Assemble the system prompt: admin-configured persona + restrictions +
     * the immutable safety footer that we never let the operator remove.
     */
    private function buildSystemPrompt(): string
    {
        $persona      = trim((string) $this->setting('ai_system_prompt', $this->defaultSystemPrompt()));
        $restrictions = trim((string) $this->setting('ai_restrictions', $this->defaultRestrictions()));
        $platformName = (string) (SystemSetting::get('platform_name') ?? 'سعرها');

        $parts = [
            $persona,
        ];

        if ($restrictions !== '') {
            $parts[] = "\n=== قيود إضافية من إدارة المنصّة / Operator restrictions ===\n" . $restrictions;
        }

        // Immutable safety footer — not configurable to prevent accidents.
        $parts[] = <<<SAFETY

=== قواعد ثابتة لا يمكن تجاوزها (HARD SAFETY) ===
- ممنوع تماماً تقديم تشخيص أو نصيحة علاجية أو جرعة دواء — وجّه إلى طبيب مختص.
- لا تخترع عيادات أو خدمات أو أسعار لم تأتِ في قسم "السياق". إذا لم يوجد سياق، اقترح طريقة البحث بدون تسمية عيادات.
- لا تتحدّث عن مواضيع سياسيّة أو دينيّة أو خارج نطاق منصّة {$platformName}.
- إذا سأل المستخدم خارج النطاق، أعِد التوجيه بلطف.
SAFETY;

        return implode("\n", $parts);
    }

    /**
     * Build the user-turn message. Includes the matched-clinics context
     * block so the LLM can reference them by name without hallucination.
     */
    private function buildUserPrompt(string $query, Collection $clinics): string
    {
        $context = $clinics->isEmpty()
            ? '— لم يجد البحث المحلّي أي عيادة مطابقة. أجب على السؤال مباشرةً بدون ذكر أسماء عيادات.'
            : $clinics->map(function (Clinic $c) {
                $line = '• ' . $c->name;
                if ($c->city)   $line .= ' — ' . $c->city->name;
                if ($c->categories?->isNotEmpty()) {
                    $line .= ' — ' . $c->categories->pluck('name')->join('، ');
                }
                if (isset($c->min_price) && $c->min_price !== null) {
                    $line .= ' — يبدأ من ' . (int) $c->min_price . ' ر.س';
                }
                if (isset($c->google_reviews_avg_rating) && $c->google_reviews_avg_rating) {
                    $line .= ' — تقييم ' . round($c->google_reviews_avg_rating, 1) . '/5';
                }
                return $line;
            })->implode("\n");

        return <<<PROMPT
=== السياق (Context) — عيادات وجدتها قاعدة البيانات ===
{$context}

=== سؤال المستخدم (User question) ===
{$query}

=== تعليمات الرد ===
- ردّ بنفس لغة السؤال (عربي إذا عربي، إنجليزي إذا إنجليزي).
- كن موجزاً (2-4 جمل عادةً).
- إذا كانت العيادات في السياق مناسبة، اذكرها بأسمائها كما هي.
- إذا لم يكن السؤال متعلّقاً بعيادات أصلاً (ترحيب، طريقة الاستخدام، شكوى)، أجب مباشرةً بدون ذكر العيادات.
PROMPT;
    }

    // ============================================================
    //   Settings helpers
    // ============================================================

    private function freeformEnabled(): bool
    {
        // Default ON when the row exists with no value; only an explicit '0'
        // disables it (the React Switch writes '0'/'1').
        $raw = SystemSetting::get('ai_freeform_enabled');
        if ($raw === null) return true;
        return (string) $raw !== '0' && $raw !== false;
    }

    private function setting(string $key, mixed $default): mixed
    {
        $val = SystemSetting::get($key);
        return ($val === null || $val === '') ? $default : $val;
    }

    private function assistantName(): string
    {
        return (string) $this->setting('ai_assistant_name', 'مساعد سعرها');
    }

    public function defaultSystemPrompt(): string
    {
        return <<<PROMPT
أنت "مساعد سعرها" — مساعد ذكي ودود لمنصّة "سعرها" السعودية لمقارنة العيادات والمراكز الطبيّة وحجز المواعيد.

ما تفعله:
- تساعد المستخدمين على إيجاد العيادات والخدمات الطبيّة المناسبة (حسب التخصّص، المدينة، السعر، التقييم).
- تشرح كيف تعمل المنصّة: البحث، الحجز، طلب عرض سعر، المقالات، التقييمات.
- تجيب على أسئلة عامّة عن المنصّة بأسلوب مهنيّ ودافئ.

أسلوبك:
- استخدم لغة المستخدم (عربية فصيحة بسيطة أو إنجليزية حسب سؤاله).
- كن موجزاً ومحدّداً — جملتان إلى أربع عادةً.
- وجّه المستخدم لخطوة عمليّة في نهاية كل ردّ (مثلاً: "اضغط على العيادة لعرض الأسعار" أو "جرّب البحث بكلمة أوضح").
- اذكر العيادات بأسمائها فقط عندما تأتي في قسم "السياق" أسفل سؤال المستخدم.
PROMPT;
    }

    public function defaultRestrictions(): string
    {
        return <<<RULES
- لا تذكر أسعاراً أو أرقاماً لم تأتِ في قسم السياق.
- لا تروّج لعيادة دون أخرى — العيادات في السياق مرتّبة بالفعل حسب نيّة السؤال.
- لا تستخدم تنسيق Markdown أو رموز ASCII لأنّ الواجهة تعرض نصاً عادياً.
- لا تستخدم رموز إيموجي أكثر من واحد في الردّ.
RULES;
    }

    // ============================================================
    //   Response wrapping + safety
    // ============================================================

    private function wrap(string $kind, string $reply, Collection $clinics): array
    {
        return [
            'kind'           => $kind,
            'reply'          => $reply,
            'clinics'        => $clinics,
            'provider'       => $this->providers->isConfigured() ? $this->providers->activeProviderName() : null,
            'assistant_name' => $this->assistantName(),
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

    // ============================================================
    //   Local clinic search (deterministic context for the LLM)
    // ============================================================

    /**
     * Tokenize the natural-language query and try each meaningful token
     * against cities + categories. The LLM never sees the raw query for
     * the purpose of *picking* clinics — that stays here so hallucinated
     * clinic names are structurally impossible.
     */
    private function matchByKeyword(string $query, ?int $cityId): Collection
    {
        $base = Clinic::publiclyVisible()->with(['city', 'categories']);

        $tokens = $this->tokenize($query);

        $resolvedCityId = $cityId ?? $this->firstMatchingCityId($tokens);
        if ($resolvedCityId) {
            $base->where('city_id', $resolvedCityId);
        }

        $category = $this->firstMatchingCategory($tokens)
            ?? $this->firstMatchingCategory([$query]);
        if ($category) {
            $base->whereHas('categories', fn($q) => $q->where('categories.id', $category->id));
        }

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

        if (! $category && ! $resolvedCityId && ! empty($tokens)) {
            $base->where(function ($q) use ($tokens) {
                foreach ($tokens as $t) {
                    $q->orWhere('name', 'like', "%{$t}%")
                      ->orWhere('description', 'like', "%{$t}%")
                      ->orWhereHas('categories', fn ($c) => $c
                          ->where('name', 'like', "%{$t}%")
                          ->orWhere('name_en', 'like', "%{$t}%"));
                }
            });
        }

        return $base->take(3)->get();
    }

    private function tokenize(string $query): array
    {
        $parts = preg_split('/[\s,،.؟?!:؛;\-]+/u', mb_strtolower($query)) ?: [];
        $clean = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '' || mb_strlen($p) < 2) continue;
            if (in_array($p, self::STOPWORDS, true)) continue;
            $clean[] = $p;
        }
        return array_values(array_unique($clean));
    }

    private function firstMatchingCityId(array $tokens): ?int
    {
        foreach ($tokens as $t) {
            $city = City::query()
                ->where(fn ($q) => $q->where('name', 'like', "%{$t}%")
                    ->orWhere('name_en', 'like', "%{$t}%"))
                ->first(['id']);
            if ($city) return $city->id;
        }
        return null;
    }

    private function firstMatchingCategory(array $tokens): ?Category
    {
        foreach ($tokens as $t) {
            $cat = Category::query()
                ->where(fn ($q) => $q->where('name', 'like', "%{$t}%")
                    ->orWhere('name_en', 'like', "%{$t}%"))
                ->first();
            if ($cat) return $cat;
        }
        return null;
    }
}
