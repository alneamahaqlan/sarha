<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only event log for the clinic-internal team. Audience: the
 * clinic owner. Parallel to `audit_logs` (which is admin-internal) so
 * the two audiences + their queries don't interfere.
 *
 * Immutable: no updated_at, no edit endpoints. Soft-deleting an actor
 * leaves the row in place because the actor_name / actor_role columns
 * are snapshots taken at write time.
 */
class ClinicActivityLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'clinic_id', 'actor_type', 'actor_id', 'actor_name', 'actor_role',
        'action', 'model_type', 'model_id', 'summary', 'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    /** The team member or Clinic owner that performed the action. */
    public function actor()
    {
        return $this->morphTo();
    }

    /** What the action operated on (Booking, Service, Complaint, ...). */
    public function subject()
    {
        return $this->morphTo(__FUNCTION__, 'model_type', 'model_id');
    }

    public function scopeForClinic(Builder $q, int $clinicId): Builder
    {
        return $q->where('clinic_id', $clinicId);
    }

    public function scopeForActor(Builder $q, string $type, int $id): Builder
    {
        return $q->where('actor_type', $type)->where('actor_id', $id);
    }
}
