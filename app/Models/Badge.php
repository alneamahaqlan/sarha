<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * An admin-managed display badge (Badges Center). Either manually attached to
 * complexes or auto-assigned by a BadgeRuleRegistry rule via badges:recompute.
 */
class Badge extends Model
{
    protected $fillable = [
        'key', 'target_types', 'label_ar', 'label_en', 'description_ar', 'description_en',
        'icon', 'color', 'placement', 'mode', 'rule_key', 'rule_params', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'target_types' => 'array',
            'rule_params'  => 'array',
            'is_active'    => 'boolean',
            'sort_order'   => 'integer',
        ];
    }

    public const MODE_MANUAL = 'manual';
    public const MODE_AUTO = 'auto';

    public const PLACEMENTS = ['header', 'cards', 'both'];

    /** Polymorphic assignments across every badgeable kind. */
    public function clinics()
    {
        return $this->morphedByMany(Clinic::class, 'badgeable')
            ->withPivot(['source', 'expires_at'])->withTimestamps();
    }

    public function offers()
    {
        return $this->morphedByMany(Offer::class, 'badgeable')
            ->withPivot(['source', 'expires_at'])->withTimestamps();
    }

    public function services()
    {
        return $this->morphedByMany(Service::class, 'badgeable')
            ->withPivot(['source', 'expires_at'])->withTimestamps();
    }

    public function doctors()
    {
        return $this->morphedByMany(Doctor::class, 'badgeable')
            ->withPivot(['source', 'expires_at'])->withTimestamps();
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** Localized label for the current app locale. */
    public function label(): string
    {
        return app()->getLocale() === 'en' ? $this->label_en : $this->label_ar;
    }

    /** Localized description (may be null). */
    public function description(): ?string
    {
        return app()->getLocale() === 'en' ? $this->description_en : $this->description_ar;
    }
}
