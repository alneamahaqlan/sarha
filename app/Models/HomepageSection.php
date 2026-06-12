<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class HomepageSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'key', 'type', 'title_ar', 'title_en',
        'is_active', 'sort_order', 'item_limit', 'banner_interval_seconds',
        'show_on_mobile', 'show_on_desktop',
        'starts_at', 'ends_at', 'config',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'show_on_mobile'  => 'boolean',
        'show_on_desktop' => 'boolean',
        'starts_at'       => 'datetime',
        'ends_at'         => 'datetime',
        'config'          => 'array',
    ];

    public const TYPES = [
        'hero', 'stats', 'banner', 'offers', 'articles',
        'categories', 'category_offers', 'ai_highlight',
        'how_it_works', 'clinic_list', 'map', 'cta',
        'price_quote', 'faqs',
    ];

    /** Max Q&A rows a `faqs` section stores / renders (config.faqs). */
    public const FAQ_LIMIT = 5;

    public const CACHE_KEY = 'homepage:sections:v1';
    public const CACHE_TTL = 600; // 10 min

    public function bannerSlides(): HasMany
    {
        return $this->hasMany(HomepageBannerSlide::class)->orderBy('sort_order');
    }

    public function scopeRenderable($q)
    {
        return $q->where('is_active', true)
            ->where(fn ($qq) => $qq->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($qq) => $qq->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderBy('sort_order');
    }

    /** Bust the public-page section cache whenever any section row changes. */
    protected static function booted(): void
    {
        $bust = fn () => Cache::forget(self::CACHE_KEY);
        static::saved($bust);
        static::deleted($bust);
    }
}
