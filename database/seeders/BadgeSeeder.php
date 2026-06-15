<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

/**
 * Seeds example automatic badges so the Badges Center works out of the box.
 * Idempotent (keyed by `key`). Admins can edit / disable / delete these and
 * add their own from the Badges Center UI.
 */
class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            [
                'key' => 'most-booked', 'label_ar' => 'الأكثر حجزًا', 'label_en' => 'Most booked',
                'icon' => 'fire', 'color' => 'red', 'placement' => 'both',
                'mode' => 'auto', 'rule_key' => 'most_booked',
                'rule_params' => ['window_days' => 30, 'top_n' => 5, 'min' => 1],
                'is_active' => true, 'sort_order' => 10,
            ],
            [
                'key' => 'fastest-growing', 'label_ar' => 'الأسرع نموًا', 'label_en' => 'Fastest growing',
                'icon' => 'trending-up', 'color' => 'emerald', 'placement' => 'both',
                'mode' => 'auto', 'rule_key' => 'fastest_growing',
                'rule_params' => ['window_days' => 7, 'top_n' => 5, 'min' => 1],
                'is_active' => true, 'sort_order' => 20,
            ],
            [
                'key' => 'top-rated', 'label_ar' => 'الأعلى تقييمًا', 'label_en' => 'Top rated',
                'icon' => 'star-solid', 'color' => 'gold', 'placement' => 'cards',
                'mode' => 'auto', 'rule_key' => 'top_rated',
                'rule_params' => ['top_n' => 5, 'min_rating' => 4.5, 'min_reviews' => 5],
                'is_active' => true, 'sort_order' => 30,
            ],
        ];

        foreach ($badges as $b) {
            Badge::updateOrCreate(['key' => $b['key']], $b);
        }
    }
}
