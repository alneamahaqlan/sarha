<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    public const REFERENCE_PREFIX = 'SAR-';

    protected $fillable = [
        'reference_code', 'clinic_id', 'user_id', 'service_id', 'customer_name',
        'customer_phone', 'notes', 'status', 'clinic_notes',
        'appointment_at', 'source',
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
