<?php

namespace App\Models;

use App\Models\Concerns\HasDemoData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Standalone promotional offer. Two flavours:
 *  - 'service' — linked to a Service; the CTA deep-links to the booking
 *    form for that exact service.
 *  - 'general' — a clinic-wide promo (seasonal sale, bundle deal); the
 *    CTA routes the visitor to call/WhatsApp the clinic.
 *
 * The "status" the admin UI shows (active / scheduled / expired / paused)
 * is derived from is_active + the [starts_at, ends_at] window — not a
 * stored column — so we never get out of sync with the clock.
 */
class Offer extends Model
{
    use HasFactory, SoftDeletes, HasDemoData;

    public const TYPE_SERVICE = 'service';
    public const TYPE_GENERAL = 'general';

    protected $fillable = [
        'clinic_id', 'service_id', 'type', 'title', 'description', 'image',
        'old_price', 'price', 'starts_at', 'ends_at',
        'is_featured', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'old_price'   => 'decimal:2',
        'price'       => 'decimal:2',
        'starts_at'   => 'datetime',
        'ends_at'     => 'datetime',
        'is_featured' => 'boolean',
        'is_active'   => 'boolean',
        'sort_order'  => 'integer',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Public visibility: active + within the running window. The clinic
     * page builder calls this; the admin UI does NOT, since admins want to
     * see scheduled + expired offers too.
     */
    public function scopeRunningNow(Builder $q): Builder
    {
        $now = now();
        return $q->where('is_active', true)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now);
    }

    public function scopeFeatured(Builder $q): Builder
    {
        return $q->where('is_featured', true);
    }

    /**
     * Derived status — used by the React admin grid to badge each card
     * and by the filter chips ("Active now / Scheduled / Expired").
     * Centralised here so the rule stays consistent across surfaces.
     */
    public function status(): string
    {
        $now = now();
        if (! $this->is_active)            { return 'paused'; }
        if ($this->starts_at?->gt($now))    { return 'scheduled'; }
        if ($this->ends_at?->lt($now))      { return 'expired'; }
        return 'active';
    }

    /**
     * Discount percentage rounded down — null when prices aren't both set
     * (general offers without prices show as a text-only promo).
     */
    public function discountPercentage(): ?int
    {
        if (! $this->old_price || ! $this->price || $this->old_price <= 0) {
            return null;
        }
        return (int) floor((($this->old_price - $this->price) / $this->old_price) * 100);
    }

    /**
     * Effective image — falls back to the linked service's image when the
     * admin didn't upload one specifically for this offer, so service-type
     * offers stay visually grounded without extra work.
     */
    public function effectiveImage(): ?string
    {
        return $this->image ?: $this->service?->image;
    }
}
