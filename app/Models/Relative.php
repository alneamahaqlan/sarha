<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A saved relative the booker can pick from when filling out the
 * booking form on behalf of someone else (parent, spouse, child, …).
 *
 * Hard cap of 3 active rows per user is enforced at the controller
 * layer — not via a DB trigger — because it stays editable from a
 * single code path (BookingController) and never needs to change.
 */
class Relative extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Hard cap on saved relatives per user. The spec calls this out
     * explicitly: the limit is a product decision, not configurable.
     */
    public const MAX_PER_USER = 3;

    /**
     * The relationship types offered in the dropdown. `other` switches
     * to a free-text label captured in `relationship_label`.
     */
    public const TYPES = [
        'father', 'mother', 'husband', 'wife', 'son', 'daughter',
        'brother', 'sister', 'grandfather', 'grandmother',
        'uncle_paternal', 'aunt_paternal', 'uncle_maternal', 'aunt_maternal',
        'other',
    ];

    protected $fillable = [
        'user_id', 'name', 'relationship_type', 'relationship_label', 'phone',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
