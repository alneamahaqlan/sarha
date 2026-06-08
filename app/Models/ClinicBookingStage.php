<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A custom Kanban column owned by a clinic. Anchored to a fixed
 * semantic `kind` (see Booking::KANBAN_GROUPS) so business logic that
 * reads bookings.status keeps working no matter how the clinic slices
 * its board. See the create_clinic_booking_stages migration.
 */
class ClinicBookingStage extends Model
{
    /** The 4 semantic kinds a stage can belong to (Booking kanban kinds). */
    public const KINDS = ['new', 'confirmed', 'completed', 'cancelled'];

    /** Allowed stage colours (mirrors the tag palette). */
    public const COLORS = ['rose', 'amber', 'emerald', 'sky', 'violet', 'slate'];

    /**
     * Default stages seeded for a clinic that hasn't customised yet —
     * reproduces today's 4-column board. [name_ar, kind, color].
     */
    public const DEFAULTS = [
        ['جديد', 'new', 'sky'],
        ['حجز', 'confirmed', 'emerald'],
        ['تمّت الخدمة', 'completed', 'violet'],
        ['ملغي', 'cancelled', 'rose'],
    ];

    /** Fixed display names for the base (is_default) stage of each kind. */
    public const BASE_NAMES = [
        'new'       => 'جديد',
        'confirmed' => 'حجز',
        'completed' => 'تمّت الخدمة',
        'cancelled' => 'ملغي',
    ];

    protected $fillable = [
        'clinic_id', 'name', 'kind', 'color', 'sort_order', 'is_default',
    ];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_default' => 'boolean'];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'stage_id');
    }
}
