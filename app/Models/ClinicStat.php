<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicStat extends Model
{
    /**
     * @deprecated The `search_appearances` column is the LEGACY impressions
     * counter. It is no longer the source of truth and is not read by any
     * user-facing surface — public «عدد الظهور» and the clinic stats page both
     * read clinic_impressions (per-source) via ImpressionTrackerService /
     * ClinicStatsService. The column is kept only for historical/seeded data
     * and is no longer written by runtime app code. Do NOT add new reads/writes
     * to it. See docs/qa/TC-001-impressions-discrepancy.md.
     */
    protected $fillable = [
        'clinic_id', 'date', 'search_appearances',
        'page_views', 'bookings_count', 'quote_requests_count',
        'whatsapp_clicks', 'call_clicks', 'booking_clicks', 'directions_clicks',
    ];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    /**
     * Bump a daily counter for the given clinic. Cannot be named `increment()`
     * — Eloquent\Model already defines a non-static `increment()` and PHP
     * refuses the static override (FatalError at class load time, which
     * silently 500'd every endpoint that touched the `stats` relation).
     */
    public static function bump(int $clinicId, string $field, int $amount = 1): void
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
