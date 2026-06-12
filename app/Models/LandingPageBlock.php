<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * One content block on a landing page. The row id is a stable sortable key for
 * the drag-and-drop builder. `config` carries the per-type shape; saving or
 * deleting a block busts the parent page's public block cache.
 */
class LandingPageBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'landing_page_id', 'type', 'sort_order', 'is_visible', 'config', 'block_version',
    ];

    protected function casts(): array
    {
        return [
            'is_visible'    => 'boolean',
            'sort_order'    => 'integer',
            'config'        => 'array',
            'block_version' => 'integer',
        ];
    }

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class);
    }

    protected static function booted(): void
    {
        $bust = fn (self $block) => Cache::forget(LandingPage::cacheKey($block->landing_page_id));
        static::saved($bust);
        static::deleted($bust);
    }
}
