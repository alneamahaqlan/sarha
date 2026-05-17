<?php

namespace App\Support;

use App\Models\Clinic;
use Illuminate\Support\Collection;

/**
 * Computes "smart" badges shown on clinic cards in search results.
 * Pure functions — no DB queries beyond what's already loaded.
 */
class SmartBadges
{
    /**
     * @return array<array{label_key: string, color: string}>
     */
    public static function compute(Clinic $clinic, Collection $context): array
    {
        $badges = [];

        // Top rated in the result set
        $maxRating = $context->max('google_reviews_avg_rating');
        if (
            ($clinic->google_reviews_avg_rating ?? 0) >= 4.5
            && ($clinic->google_reviews_avg_rating ?? 0) === $maxRating
        ) {
            $badges[] = ['label_key' => 'site.badge_top_rated', 'color' => 'yellow'];
        }

        // Best price in the result set
        $minPrice = $context->whereNotNull('min_price')->min('min_price');
        if ($minPrice && ($clinic->min_price ?? null) === $minPrice) {
            $badges[] = ['label_key' => 'site.badge_best_price', 'color' => 'emerald'];
        }

        // Most booked
        $maxBookings = $context->max('bookings_count');
        if ($maxBookings && ($clinic->bookings_count ?? 0) === $maxBookings && $maxBookings >= 5) {
            $badges[] = ['label_key' => 'site.badge_most_booked', 'color' => 'red'];
        }

        // Featured shorthand (always last so it doesn't crowd)
        if ($clinic->is_featured) {
            $badges[] = ['label_key' => 'site.badge_featured_short', 'color' => 'amber'];
        }

        return $badges;
    }
}
