<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-managed safety rule consulted by the AiAssistantInterceptor.
 * See the migration docblock for the meaning of each `type`.
 *
 * The list is small (dozens, not thousands) and read on every chat turn,
 * so we cache it for a minute. The cache busts on save/delete.
 */
class AiRestriction extends Model
{
    use HasFactory;

    public const TYPE_BANNED_TOPIC       = 'banned_topic';
    public const TYPE_EMERGENCY_KEYWORD  = 'emergency_keyword';
    public const TYPE_CLINIC_BLOCKLIST   = 'clinic_blocklist';
    public const TYPE_CATEGORY_BLOCKLIST = 'category_blocklist';

    public const TYPES = [
        self::TYPE_BANNED_TOPIC,
        self::TYPE_EMERGENCY_KEYWORD,
        self::TYPE_CLINIC_BLOCKLIST,
        self::TYPE_CATEGORY_BLOCKLIST,
    ];

    public const CACHE_KEY = 'ai:restrictions:v1';

    protected $fillable = [
        'type', 'value', 'response_override', 'is_active', 'created_by_admin_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    protected static function booted(): void
    {
        $bust = fn () => Cache::forget(self::CACHE_KEY);
        static::saved($bust);
        static::deleted($bust);
    }
}
