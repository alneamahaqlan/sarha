<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

/**
 * Admin-managed header/footer navigation link. The public layout reads the
 * active links (cached per location) through a View Composer. A link resolves
 * its href from a static page, then a named route, then a raw URL.
 */
class NavigationLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'location', 'footer_column', 'label_ar', 'label_en',
        'url', 'static_page_id', 'route_name',
        'open_new_tab', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'footer_column' => 'integer',
            'open_new_tab'  => 'boolean',
            'is_active'     => 'boolean',
        ];
    }

    public const CACHE_HEADER = 'navigation_links:header';
    public const CACHE_FOOTER = 'navigation_links:footer';

    /** Bust both location caches whenever any link row changes. */
    protected static function booted(): void
    {
        $bust = function () {
            Cache::forget(self::CACHE_HEADER);
            Cache::forget(self::CACHE_FOOTER);
        };
        static::saved($bust);
        static::deleted($bust);
    }

    public function staticPage(): BelongsTo
    {
        return $this->belongsTo(StaticPage::class);
    }

    public function scopeActiveFor(Builder $query, string $location): Builder
    {
        return $query->where('location', $location)
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    /** Locale-aware label — falls back to Arabic when the English value is blank. */
    public function getLabelAttribute(): string
    {
        return app()->getLocale() === 'en' && filled($this->label_en)
            ? $this->label_en
            : (string) $this->label_ar;
    }

    /**
     * Resolve the href: prefer a linked static page, then a named route, then
     * a raw URL. Returns '#' when nothing resolves (e.g. a route was removed)
     * so the public template never emits a broken link.
     */
    public function getResolvedUrlAttribute(): string
    {
        if ($this->static_page_id && $this->staticPage) {
            return url('/' . $this->staticPage->slug);
        }

        if ($this->route_name && Route::has($this->route_name)) {
            return route($this->route_name);
        }

        return $this->url ?: '#';
    }
}
