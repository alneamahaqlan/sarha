<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-managed static content page (About, Privacy, Terms, Contact, FAQ).
 * Bilingual title + rich-HTML body. The public side reads a single page by
 * slug via the `published` scope; the per-slug cache is busted on every save.
 */
class StaticPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug', 'title_ar', 'title_en', 'body_ar', 'body_en',
        'meta_description_ar', 'meta_description_en',
        'is_active', 'is_system', 'published_at', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active'    => 'boolean',
            'is_system'    => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public const CACHE_PREFIX = 'static_page:';

    /** Bust the public per-slug cache whenever a page row changes. */
    protected static function booted(): void
    {
        $bust = fn (self $page) => Cache::forget(self::CACHE_PREFIX . $page->slug);
        static::saved($bust);
        static::deleted($bust);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /** Locale-aware title — falls back to Arabic when the English value is blank. */
    public function getTitleAttribute(): string
    {
        return app()->getLocale() === 'en' && filled($this->title_en)
            ? $this->title_en
            : (string) $this->title_ar;
    }

    public function getBodyAttribute(): ?string
    {
        return app()->getLocale() === 'en' && filled($this->body_en)
            ? $this->body_en
            : $this->body_ar;
    }

    public function getMetaDescriptionAttribute(): ?string
    {
        return app()->getLocale() === 'en' && filled($this->meta_description_en)
            ? $this->meta_description_en
            : $this->meta_description_ar;
    }
}
