<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-clinic customer entity. One row per (clinic_id, normalized phone).
 *
 * Lifecycle:
 *   - Created/looked-up by CustomerLinker on Booking/Complaint/
 *     PriceQuoteRequest create.
 *   - Counter columns (total_*, completed_bookings) maintained by the
 *     same three observers via atomic ->increment() calls.
 *   - is_vip / is_repeat / is_new / has_prior_complaint are computed
 *     accessors over the persisted counters — cheap (no extra query).
 */
class Customer extends Model
{
    use HasFactory;

    public const VIP_THRESHOLD    = 5;
    public const REPEAT_THRESHOLD = 2;

    public const TYPE_BOOKING       = 'booking';
    public const TYPE_COMPLAINT     = 'complaint';
    public const TYPE_QUOTE_REQUEST = 'quote_request';

    protected $fillable = [
        'clinic_id', 'phone', 'name', 'email', 'user_id',
        'first_seen_at', 'last_seen_at',
        'last_interaction_at', 'last_interaction_type',
        'total_bookings', 'completed_bookings',
        'total_complaints', 'total_quote_requests',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at'       => 'datetime',
            'last_seen_at'        => 'datetime',
            'last_interaction_at' => 'datetime',
            'total_bookings'      => 'integer',
            'completed_bookings'  => 'integer',
            'total_complaints'    => 'integer',
            'total_quote_requests'=> 'integer',
        ];
    }

    // ---------- relations ----------
    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function complaints()
    {
        return $this->hasMany(Complaint::class);
    }

    public function priceQuoteRequests()
    {
        return $this->hasMany(PriceQuoteRequest::class);
    }

    // ---------- computed badges (derived from persisted counters) ----------
    public function getIsVipAttribute(): bool
    {
        return $this->completed_bookings >= self::VIP_THRESHOLD;
    }

    public function getIsRepeatAttribute(): bool
    {
        return $this->completed_bookings >= self::REPEAT_THRESHOLD
            && $this->completed_bookings <  self::VIP_THRESHOLD;
    }

    public function getIsNewAttribute(): bool
    {
        // "first contact, no booking yet" — distinct from "is_repeat=false"
        // (which only means <2 completed; the customer may still have 1).
        return $this->total_bookings === 0;
    }

    public function getHasPriorComplaintAttribute(): bool
    {
        return $this->total_complaints > 0;
    }

    // ---------- scopes ----------
    public function scopeForClinic(Builder $q, int $clinicId): Builder
    {
        return $q->where('clinic_id', $clinicId);
    }
}
