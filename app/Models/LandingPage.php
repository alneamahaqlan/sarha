<?php

namespace App\Models;

use App\Models\Concerns\HasDemoData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

/**
 * A super-admin built landing page, served publicly at /l/{slug}. The `type`
 * drives which entity data is auto-pulled and which default blocks are seeded;
 * the ordered `blocks` are the drag-and-drop builder's backing store.
 */
class LandingPage extends Model
{
    use HasFactory, SoftDeletes, HasDemoData;

    protected $fillable = [
        'type', 'status', 'slug', 'title_ar', 'title_en', 'internal_name',
        'clinic_id', 'offer_id', 'city_id', 'category_id',
        'cover_image', 'social_image',
        'published_at', 'starts_at', 'ends_at',
        'cta_label_ar', 'cta_label_en', 'cta_url', 'cta_style',
        'whatsapp_phone', 'call_phone', 'whatsapp_enabled', 'call_enabled',
        'header_mode', 'footer_mode', 'header_config', 'footer_config',
        'seo_title_ar', 'seo_title_en', 'seo_description_ar', 'seo_description_en',
        'seo_keywords', 'canonical_url', 'meta_robots', 'in_sitemap',
        'og_title_ar', 'og_title_en', 'og_description_ar', 'og_description_en',
        'schema_markup', 'schema_type', 'schema_version', 'created_by',
        // Clinic ownership + one-time approval gate (see scopePubliclyLive).
        'owner_clinic_id', 'approval_status', 'approval_reason',
        'submitted_at', 'reviewed_at', 'approval_reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'published_at'     => 'datetime',
            'starts_at'        => 'datetime',
            'ends_at'          => 'datetime',
            'submitted_at'     => 'datetime',
            'reviewed_at'      => 'datetime',
            'whatsapp_enabled' => 'boolean',
            'call_enabled'     => 'boolean',
            'header_config'    => 'array',
            'footer_config'    => 'array',
            'in_sitemap'       => 'boolean',
            'schema_markup'    => 'array',
            'schema_version'   => 'integer',
            'total_views'      => 'integer',
            'total_conversions'=> 'integer',
        ];
    }

    public const TYPES = ['clinic', 'offer', 'city', 'category', 'comparison', 'custom'];

    public const STATUSES = ['draft', 'published', 'archived'];

    /**
     * One-time approval lifecycle for clinic-owned pages. Admin-built pages are
     * created `approved` and never leave it. Public visibility requires
     * `approved` regardless of who owns the page (see scopePubliclyLive).
     */
    public const APPROVAL_STATUSES = ['draft', 'pending', 'approved', 'rejected'];

    /** Footer chrome modes: platform default, logo-only, fully custom, or hidden. */
    public const CHROME_MODES = ['default', 'minimal', 'custom', 'none'];

    /** Header adds a 5th mode: render the linked clinic's full profile hero. */
    public const HEADER_CHROME_MODES = ['default', 'minimal', 'custom', 'none', 'clinic'];

    /** The block types the builder can place. Order here is unimportant. */
    public const BLOCK_TYPES = [
        'hero', 'services', 'offers', 'doctors', 'gallery',
        'reviews', 'faq', 'map', 'booking', 'countdown',
    ];

    public static function cacheKey(int $id): string
    {
        return "landing_page:blocks:v1:{$id}";
    }

    /** A page that is live right now: approved, published and within its window. */
    public function scopePubliclyLive(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where('approval_status', 'approved')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    /** Pages owned by a given complex (clinic-built, as opposed to admin-built). */
    public function scopeOwnedBy(Builder $query, int $clinicId): Builder
    {
        return $query->where('owner_clinic_id', $clinicId);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(LandingPageBlock::class)->orderBy('sort_order');
    }

    public function clinics(): BelongsToMany
    {
        return $this->belongsToMany(Clinic::class, 'landing_page_clinics')
            ->withPivot(['sort_order', 'highlight'])
            ->orderBy('landing_page_clinics.sort_order');
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(LandingPageVisit::class);
    }

    public function stats(): HasMany
    {
        return $this->hasMany(LandingPageStat::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /** The complex that owns this page (clinic-built pages only; else null). */
    public function ownerClinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class, 'owner_clinic_id');
    }

    /** The admin who approved/rejected this clinic-built page. */
    public function approvalReviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approval_reviewed_by');
    }

    /** Locale-aware title — falls back to Arabic when the English value is blank. */
    public function getTitleAttribute(): ?string
    {
        return app()->getLocale() === 'en' && filled($this->title_en)
            ? $this->title_en
            : $this->title_ar;
    }

    protected static function booted(): void
    {
        $bust = fn (self $page) => Cache::forget(self::cacheKey($page->id));
        static::saved($bust);
        static::deleted($bust);
    }
}
