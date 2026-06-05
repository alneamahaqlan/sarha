<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A "contact this patient at time X" reminder a clinic sets for itself.
 *
 * Lifecycle:
 *   - Created from the Customer Hub or a booking card (status = pending).
 *   - saerha:dispatch-contact-reminders rings the clinic bell once
 *     remind_at passes, then stamps notified_at (idempotent).
 *   - The clinic marks it done or cancels it from the reminders page.
 *
 * Audience is the whole clinic (shared bell) — created_by_* is just a
 * snapshot of who scheduled it.
 */
class CustomerReminder extends Model
{
    use HasFactory;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_DONE       = 'done';
    public const STATUS_CANCELLED  = 'cancelled';

    protected $fillable = [
        'clinic_id', 'customer_id', 'booking_id', 'assignee_member_id',
        'remind_at', 'note', 'status',
        'created_by_type', 'created_by_id', 'created_by_name',
        'notified_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'remind_at'    => 'datetime',
            'notified_at'  => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // ---------- relations ----------
    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function createdBy()
    {
        return $this->morphTo('created_by');
    }

    /** Optional team member the reminder is assigned to (nullable). */
    public function assigneeMember()
    {
        return $this->belongsTo(ClinicTeamMember::class, 'assignee_member_id');
    }

    // ---------- computed ----------
    /** Pending and its time has already passed — the clinic is late. */
    public function getIsOverdueAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->remind_at
            && $this->remind_at->isPast();
    }

    // ---------- scopes ----------
    public function scopeForClinic(Builder $q, int $clinicId): Builder
    {
        return $q->where('clinic_id', $clinicId);
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PENDING);
    }

    /** Pending, time has come, and not yet notified — the scheduler's set. */
    public function scopeDue(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PENDING)
            ->whereNull('notified_at')
            ->where('remind_at', '<=', now());
    }
}
