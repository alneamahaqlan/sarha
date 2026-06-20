<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A verified review: tied to a booking with a confirmed attendance, so we
 * KNOW the author actually visited. One per attended booking.
 *
 * Non-coercive: a genuine negative is stored + shown like any other; the
 * clinic replies publicly. `is_visible` is admin moderation for spam/abuse
 * only — never a tool to hide a real negative. See the migration for the
 * full rationale.
 */
class VerifiedReview extends Model
{
    public const REFERENCE_PREFIX = 'REV-';

    public const STATUS_PENDING   = 'pending';   // eligible, awaiting submission
    public const STATUS_PUBLISHED = 'published'; // submitted by the patient

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_PUBLISHED];

    public const MIN_RATING = 1;
    public const MAX_RATING = 5;

    /** Why a clinic flags a review for admin review. NEVER "negative". */
    public const REPORT_REASONS = ['spam', 'abuse', 'fake', 'other'];
    public const MODERATION_HIDDEN    = 'hidden';
    public const MODERATION_DISMISSED = 'dismissed';

    protected $fillable = [
        'reference_code',
        'clinic_id', 'booking_id', 'customer_id', 'user_id', 'doctor_id',
        'customer_name', 'customer_phone',
        'clinic_rating', 'doctor_rating', 'comment',
        'status', 'is_visible',
        'clinic_reply_text',
        'clinic_replied_by_member_id',
        'clinic_replied_by_name_snapshot',
        'clinic_replied_by_role_snapshot',
        'clinic_replied_at',
        'invited_at', 'submitted_at',
        'reported_at', 'report_reason', 'report_note', 'reported_by_name',
        'moderated_at', 'moderated_by_admin_id', 'moderation_action', 'moderation_reason',
    ];

    protected function casts(): array
    {
        return [
            'clinic_rating'     => 'integer',
            'doctor_rating'     => 'integer',
            'is_visible'        => 'boolean',
            'clinic_replied_at' => 'datetime',
            'invited_at'        => 'datetime',
            'submitted_at'      => 'datetime',
            'reported_at'       => 'datetime',
            'moderated_at'      => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (VerifiedReview $review) {
            if (empty($review->reference_code)) {
                $review->reference_code = static::generateReferenceCode();
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

    // ---------- relations ----------
    public function clinic(): BelongsTo   { return $this->belongsTo(Clinic::class); }
    public function booking(): BelongsTo   { return $this->belongsTo(Booking::class); }
    public function customer(): BelongsTo  { return $this->belongsTo(Customer::class); }
    public function user(): BelongsTo      { return $this->belongsTo(User::class); }
    public function doctor(): BelongsTo    { return $this->belongsTo(Doctor::class); }

    public function clinicRepliedByMember(): BelongsTo
    {
        return $this->belongsTo(ClinicTeamMember::class, 'clinic_replied_by_member_id');
    }

    // ---------- state helpers ----------
    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function hasReply(): bool
    {
        return ! empty($this->clinic_reply_text);
    }

    public function isReported(): bool
    {
        return $this->reported_at !== null;
    }

    /** Reported but not yet decided by an admin. */
    public function isPendingModeration(): bool
    {
        return $this->reported_at !== null && $this->moderated_at === null;
    }

    public function moderatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Admin::class, 'moderated_by_admin_id');
    }

    /** The admin moderation queue: reported, not yet decided. */
    public function scopeReportedPending(Builder $q): Builder
    {
        return $q->whereNotNull('reported_at')->whereNull('moderated_at');
    }

    // ---------- scopes ----------
    /** Publicly shown reviews: submitted AND not moderated out. */
    public function scopeVisible(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PUBLISHED)->where('is_visible', true);
    }
}
