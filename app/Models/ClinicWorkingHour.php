<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicWorkingHour extends Model
{
    public const DAYS = [
        0 => 'sunday',
        1 => 'monday',
        2 => 'tuesday',
        3 => 'wednesday',
        4 => 'thursday',
        5 => 'friday',
        6 => 'saturday',
    ];

    protected $fillable = [
        'clinic_id', 'day_of_week', 'is_open', 'opens_at', 'closes_at',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_open'     => 'boolean',
        ];
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function getDayKeyAttribute(): string
    {
        return self::DAYS[$this->day_of_week] ?? 'unknown';
    }
}
