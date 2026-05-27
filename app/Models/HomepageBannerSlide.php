<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class HomepageBannerSlide extends Model
{
    use HasFactory;

    protected $fillable = [
        'homepage_section_id', 'image', 'link_url', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(HomepageSection::class, 'homepage_section_id');
    }

    /** Slides affect the banner-section render — bust the same cache. */
    protected static function booted(): void
    {
        $bust = fn () => Cache::forget(HomepageSection::CACHE_KEY);
        static::saved($bust);
        static::deleted($bust);
    }
}
