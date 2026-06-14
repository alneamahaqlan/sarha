<?php

namespace App\Support;

/**
 * Single source of truth for the badge icon set: each curated icon key mapped
 * to an Arabic display name shown in the admin's visual icon picker.
 *
 * Every key here MUST also exist in:
 *   - resources/views/components/icon.blade.php  (Blade x-icon SVG)
 *   - resources/react-admin/src/features/badges/components/BadgeChip.tsx  (lucide map)
 * Keep the three in sync when adding an icon.
 */
class BadgeIcons
{
    /** @return array<string,string> key => Arabic label */
    public static function all(): array
    {
        return [
            // ── core set ──
            'star-solid'      => 'نجمة',
            'check-circle'    => 'علامة صح',
            'check-badge'     => 'موثّق',
            'shield-check'    => 'درع الثقة',
            'trophy'          => 'كأس',
            'fire'            => 'الأكثر رواجًا',
            'trending-up'     => 'تصاعد',
            'rocket-launch'   => 'الأسرع',
            'bolt'            => 'سريع',
            'sparkles'        => 'بريق',
            'heart-solid'     => 'قلب',
            'hand-thumb-up'   => 'إعجاب',
            // ── audience / activity ──
            'users'           => 'جمهور',
            'user-plus'       => 'متابعون',
            'eye'             => 'مشاهدات',
            'calendar'        => 'حجوزات',
            'clock'           => 'سرعة الرد',
            'bell'            => 'تنبيه',
            // ── commercial ──
            'tag'             => 'سعر',
            'receipt-percent' => 'خصم',
            'gift'            => 'هدية',
            'ticket'          => 'تذكرة',
            'megaphone'       => 'إعلان',
            // ── expertise / misc ──
            'academic-cap'    => 'خبرة',
            'building'        => 'مجمع',
            'light-bulb'      => 'فكرة',
        ];
    }

    /** Ordered list of valid keys (for validation Rule::in). */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /** Closure-free meta for the admin picker: [{key,label_ar}]. */
    public static function meta(): array
    {
        return collect(self::all())
            ->map(fn ($label, $key) => ['key' => $key, 'label_ar' => $label])
            ->values()
            ->all();
    }
}
