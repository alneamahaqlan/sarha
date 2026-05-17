<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Complaint extends Model
{
    use SoftDeletes;

    public const REFERENCE_PREFIX = 'CMP-';

    public const TYPES = ['quality', 'pricing', 'misleading_info', 'other'];
    public const STATUSES = ['new', 'in_review', 'resolved', 'rejected'];
    public const PRIORITIES = ['low', 'medium', 'high'];

    protected $fillable = [
        'reference_code',
        'clinic_id', 'user_id', 'booking_id',
        'customer_name', 'customer_phone', 'customer_email',
        'type', 'status', 'priority',
        'subject', 'description',
        'admin_notes', 'resolution',
        'assigned_admin_id', 'resolved_at', 'clinic_notified',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at'     => 'datetime',
            'clinic_notified' => 'boolean',
        ];
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
