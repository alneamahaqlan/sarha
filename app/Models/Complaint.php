<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Complaint extends Model
{
    use HasFactory, SoftDeletes;

    public const REFERENCE_PREFIX = 'CMP-';

    public const TYPES = ['quality', 'pricing', 'misleading_info', 'other'];
    public const STATUSES = ['new', 'in_review', 'resolved', 'rejected'];
    public const PRIORITIES = ['low', 'medium', 'high'];
    public const SOURCES = ['admin', 'customer', 'clinic'];

    protected $fillable = [
        'reference_code',
        'clinic_id', 'user_id', 'booking_id', 'source',
        'customer_name', 'customer_phone', 'customer_email',
        'type', 'status', 'priority',
        'subject', 'description',
        'admin_notes', 'resolution',
        'assigned_admin_id', 'resolved_at', 'clinic_notified',
        // Clinic-side reply (Phase: team members)
        'clinic_reply_text',
        'clinic_replied_by_member_id',
        'clinic_replied_by_name_snapshot',
        'clinic_replied_by_role_snapshot',
        'clinic_replied_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at'       => 'datetime',
            'clinic_notified'   => 'boolean',
            'clinic_replied_at' => 'datetime',
        ];
    }

    /** The team member who replied on behalf of the clinic (nullable). */
    public function clinicRepliedByMember()
    {
        return $this->belongsTo(ClinicTeamMember::class, 'clinic_replied_by_member_id');
    }

    protected static function booted(): void
    {
        static::creating(function (Complaint $complaint) {
            if (empty($complaint->reference_code)) {
                $complaint->reference_code = static::generateReferenceCode();
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function assignedAdmin()
    {
        return $this->belongsTo(Admin::class, 'assigned_admin_id');
    }
}
