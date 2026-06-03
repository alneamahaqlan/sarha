<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A canonical, platform-wide service in the unified catalog. Clinic `services`
 * link to one of these so the same concept ("Teeth cleaning") is comparable
 * across every clinic that offers it.
 *
 * A row with status='pending' doubles as a clinic's request to introduce a
 * new canonical service — admins approve (→active) or reject. This mirrors the
 * CategoryRequest flow but lives on the catalog row itself (single source of
 * truth: there is no separate requests table to keep in sync).
 */
class CatalogService extends Model
{
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_PENDING  = 'pending';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'name', 'name_en', 'aliases', 'slug', 'default_description',
        'category_id', 'status', 'requested_by_clinic_id', 'reviewed_by',
    ];

    protected $casts = [
        'aliases' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** The clinic that requested this entry (only set for pending/rejected rows). */
    public function requestedByClinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class, 'requested_by_clinic_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    /** Clinic service instances linked to this canonical service. */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /** Only catalog entries clinics can pick / customers can match against. */
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_ACTIVE);
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PENDING);
    }
}
