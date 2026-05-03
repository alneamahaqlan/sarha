<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicStat extends Model
{
    protected $fillable = [
        'clinic_id', 'date', 'search_appearances',
        'page_views', 'bookings_count', 'quote_requests_count',
    ];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public static function increment(int $clinicId, string $field, int $amount = 1): void
    {
        static::firstOrCreate(
            ['clinic_id' => $clinicId, 'date' => today()],
        )->increment($field, $amount);
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }
}
