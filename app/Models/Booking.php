<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    public const REFERENCE_PREFIX = 'SAR-';

    // The 6 storage statuses (DB enum), grouped into the 4 Kanban
    // columns by KANBAN_GROUPS. Keep these in sync with the bookings
    // migration enum.
    public const STATUS_NEW              = 'new';
    public const STATUS_CONTACTED        = 'contacted';
    public const STATUS_APPOINTMENT_SET  = 'appointment_set';
    public const STATUS_COMPLETED        = 'completed';
    public const STATUS_NO_SHOW          = 'no_show';
    public const STATUS_CANCELLED        = 'cancelled';

    public const KANBAN_GROUPS = [
        'new'       => [self::STATUS_NEW, self::STATUS_CONTACTED],
        'confirmed' => [self::STATUS_APPOINTMENT_SET],
        'completed' => [self::STATUS_COMPLETED, self::STATUS_NO_SHOW],
        'cancelled' => [self::STATUS_CANCELLED],
    ];

    protected $fillable = [
        'reference_code', 'clinic_id', 'customer_id',
        'user_id', 'booker_user_id', 'relative_id',
        'service_id', 'customer_name', 'customer_phone', 'notes', 'status',
        'clinic_notes', 'appointment_at', 'source',
        'assignee_type', 'assignee_id',
    ];

    protected function casts(): array
    {
        return [
            'appointment_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            if (empty($booking->reference_code)) {
                $booking->reference_code = static::generateReferenceCode();
            }
        });
    }

    public static function generateReferenceCode(): string
    {
        do {
            $code = self::REFERENCE_PREFIX . strtoupper(Str::random(6));
        } while (static::where('reference_code', $code)->exists());

        return $code;
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * The account holder who actually placed the booking. Null for
     * legacy rows (pre-feature) — fall back to user() when reading.
     */
    public function booker()
    {
        return $this->belongsTo(User::class, 'booker_user_id');
    }

    public function relative()
    {
        return $this->belongsTo(Relative::class)->withTrashed();
    }

    /** Convenience flag the React/Blade views can render off. */
    public function isForRelative(): bool
    {
        return ! is_null($this->relative_id);
    }

    /**
     * Polymorphic assignee — either Clinic (the owner) or
     * ClinicTeamMember. Null = "غير مَسنَد".
     */
    public function assignee()
    {
        return $this->morphTo();
    }

    public function tags()
    {
        return $this->hasMany(BookingTag::class);
    }

    public function scopeForKanbanColumn(Builder $q, string $column): Builder
    {
        $statuses = self::KANBAN_GROUPS[$column] ?? [];
        return $q->whereIn('status', $statuses);
    }
}
