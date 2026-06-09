<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One cart entry — polymorphic to a Service, Offer, or Package (mirrors
 * SavedItem). `clinic_id` is denormalised on add so the cart groups + gates
 * per clinic without loading every target. `cartable()` keeps soft-deleted
 * targets so a removed service still renders (labelled "محذوف") and can be
 * cleared.
 */
class CartItem extends Model
{
    protected $fillable = [
        'user_id', 'cartable_type', 'cartable_id', 'clinic_id', 'booked_at',
    ];

    protected function casts(): array
    {
        return [
            'booked_at' => 'datetime',
        ];
    }

    public function cartable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
