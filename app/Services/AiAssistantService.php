<?php

namespace App\Services;

use App\Models\Category;
use App\Models\City;
use App\Models\Clinic;
use Illuminate\Support\Collection;

/**
 * Local-first chatbot that matches free-text Arabic/English queries
 * against existing categories + cities + clinic names. Falls back to
 * Anthropic when configured; safety-rejects medical questions.
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

    public function quickPrompts(): array
    {
        return self::QUICK_PROMPTS;
    }

    /**
     * Main entry. Returns array { reply: string, clinics: Collection, kind: string }.
     */
    public function ask(string $query, ?int $cityId = null): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['kind' => 'empty', 'reply' => __('site.ai_empty'), 'clinics' => collect()];
        }

        // 1) Medical safety filter
        if ($this->looksMedical($query)) {
            return [
                'kind'    => 'rejected',
                'reply'   => __('site.ai_medical_disclaimer'),
                'clinics' => $this->matchByKeyword($query, $cityId),
            ];
        }

        // 2) Local keyword match
        $clinics = $this->matchByKeyword($query, $cityId);

        if ($clinics->isEmpty()) {
            return [
                'kind'    => 'no_match',
                'reply'   => __('site.ai_no_match'),
                'clinics' => collect(),
            ];
        }

        return [
            'kind'    => 'matched',
            'reply'   => __('site.ai_found_matches', ['count' => $clinics->count()]),
            'clinics' => $clinics,
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
