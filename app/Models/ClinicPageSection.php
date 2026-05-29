<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class ClinicPageSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id', 'key', 'is_active', 'sort_order',
        'title_ar', 'title_en', 'item_limit',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
        'item_limit' => 'integer',
    ];

    /**
     * The fixed set of section keys. Order here is the default render order
     * applied when a clinic's page builder is seeded.
     */
    public const KEYS = [
        'hero',
        'offers',
        'packages',
        'services',
        'sub_clinics',
        'doctors',
        'before_after',
        'google_reviews',
        'articles',
        'about',
        'contact_info',
        'working_hours',
        'social_links',
        'similar_services',
        'floating_ctas',
    ];

    /**
     * Keys the clinic admin is NOT allowed to hide (still reorderable).
     * These either hold core info (hero / contact_info) or carry the page's
     * primary conversion CTAs (floating_ctas).
     */
    public const PROTECTED_KEYS = [
        'hero',
        'contact_info',
        'floating_ctas',
    ];

    public static function cacheKey(int $clinicId): string
    {
        return "clinic:page_sections:v1:{$clinicId}";
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function isProtected(): bool
    {
        return in_array($this->key, self::PROTECTED_KEYS, true);
    }

    protected static function booted(): void
    {
        $bust = function (self $row): void {
            Cache::forget(self::cacheKey($row->clinic_id));
        };
        static::saved($bust);
        static::deleted($bust);
    }
}
