<?php

namespace App\Models;

use App\Enums\ClinicRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A non-owner login that belongs to a Clinic. The owner stays on the
 * `clinics` table; members live here. Login flow finds the member by
 * phone, verifies the password, then authenticates the owning Clinic
 * via the `clinic` guard and stamps the session with this member's id.
 *
 * Soft-deleted members keep their FK on activity_logs / replies so
 * the audit trail stays intact ("Mohammed (removed)" still readable).
 */
class ClinicTeamMember extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'clinic_id', 'name', 'phone', 'password', 'role',
        'is_active', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    /**
     * The activity rows this member has triggered (polymorphic actor).
     * Used by the per-member activity drill-down page.
     */
    public function activityLogs()
    {
        return $this->morphMany(ClinicActivityLog::class, 'actor');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function roleEnum(): ClinicRole
    {
        return ClinicRole::from($this->role);
    }
}
