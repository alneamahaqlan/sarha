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

    /** Arabic status labels — used by server-side CSV exports. */
    public const STATUS_LABELS_AR = [
        self::STATUS_NEW             => 'جديد',
        self::STATUS_CONTACTED       => 'تم التواصل',
        self::STATUS_APPOINTMENT_SET => 'موعد مؤكد',
        self::STATUS_COMPLETED       => 'مكتمل',
        self::STATUS_NO_SHOW         => 'لم يحضر',
        self::STATUS_CANCELLED       => 'ملغي',
    ];

    /**
     * Acquisition channels — where the patient came from. Filled by the
     * team on manual bookings; 'other' is the default/fallback. Keep in
     * sync with ACQUISITION_SOURCES in the React admin (kanban/types.ts).
     */
    public const ACQUISITION_SOURCES = [
        'returning_customer',
        'walk_in',
        'phone_call',
        'whatsapp',
        'referral',
        'instagram',
        'snapchat',
        'tiktok',
        'twitter',
        'facebook',
        'google_maps',
        'cart',
        'other',
    ];

    /** Arabic acquisition-source labels — used by server-side CSV exports. */
    public const ACQUISITION_SOURCE_LABELS_AR = [
        'returning_customer' => 'عميل سابق',
        'walk_in'            => 'عميل أتى إلى المجمع',
        'phone_call'         => 'اتصال هاتفي',
        'whatsapp'           => 'واتساب',
        'referral'           => 'توصية من عميل آخر',
        'instagram'          => 'انستقرام',
        'snapchat'           => 'سناب شات',
        'tiktok'             => 'تيك توك',
        'twitter'            => 'تويتر (X)',
        'facebook'           => 'فيسبوك',
        'google_maps'        => 'جوجل ماب',
        'cart'               => 'سلة متروكة',
        'other'              => 'أخرى',
    ];

    protected $fillable = [
        'reference_code', 'clinic_id', 'customer_id',
        'user_id', 'booker_user_id', 'relative_id',
        'service_id', 'customer_name', 'customer_phone', 'notes', 'status',
        'clinic_notes', 'appointment_at', 'source', 'acquisition_source',
        'assignee_type', 'assignee_id', 'stage_id',
        'created_by_type', 'created_by_id', 'created_by_name',
        'landing_page_id', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
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

            // Stamp the creator from the acting clinic user (team member
            // or owner) unless it was already set explicitly. Stays null
            // for customer self-booked rows and background/seeder runs
            // where no clinic session is active.
            if (empty($booking->created_by_id) && \App\Support\ActingClinicUser::actorId()) {
                $booking->created_by_type = \App\Support\ActingClinicUser::actorType();
                $booking->created_by_id   = \App\Support\ActingClinicUser::actorId();
                $booking->created_by_name = \App\Support\ActingClinicUser::actorName() ?? '—';
            }
        });
    }

    public function createdBy()
    {
        return $this->morphTo('created_by');
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

    /** The landing page this booking originated from, if any. */
    public function landingPage()
    {
        return $this->belongsTo(LandingPage::class);
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

    /** The custom Kanban stage this booking currently sits in (nullable). */
    public function stage()
    {
        return $this->belongsTo(ClinicBookingStage::class, 'stage_id');
    }

    public function scopeForKanbanColumn(Builder $q, string $column): Builder
    {
        $statuses = self::KANBAN_GROUPS[$column] ?? [];
        return $q->whereIn('status', $statuses);
    }

    /**
     * The semantic kanban kind (new|confirmed|completed|cancelled) for a
     * given storage status. Inverse of KANBAN_GROUPS.
     */
    public static function kindForStatus(?string $status): string
    {
        foreach (self::KANBAN_GROUPS as $kind => $statuses) {
            if (in_array($status, $statuses, true)) {
                return $kind;
            }
        }
        return 'new';
    }

    /**
     * Bookings shown in a given custom stage. A booking belongs to a
     * stage when its stage_id matches; when the stage is the PRIMARY one
     * for its kind, it also absorbs un-staged rows (stage_id null) whose
     * status falls in that kind — so legacy rows and status-only edits
     * land in the right column without a backfill.
     */
    public function scopeForStage(Builder $q, ClinicBookingStage $stage, bool $isPrimary): Builder
    {
        return $q->where(function (Builder $w) use ($stage, $isPrimary) {
            $w->where('stage_id', $stage->id);
            if ($isPrimary) {
                $statuses = self::KANBAN_GROUPS[$stage->kind] ?? [];
                $w->orWhere(fn (Builder $u) => $u->whereNull('stage_id')->whereIn('status', $statuses));
            }
        });
    }
}
