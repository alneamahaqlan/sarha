<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Daily rollup counters for a landing page. Mirrors ClinicStat — `bump()`
 * upserts today's row and increments a field. Cannot be named `increment()`
 * (Eloquent already defines a non-static one).
 */
class LandingPageStat extends Model
{
    protected $fillable = [
        'landing_page_id', 'date',
        'page_views', 'unique_visitors', 'clicks', 'calls', 'whatsapp_clicks',
        'bookings_count', 'conversions', 'visits', 'bounces', 'sum_duration_seconds',
    ];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public static function bump(int $landingPageId, string $field, int $amount = 1): void
    {
        static::firstOrCreate(
            ['landing_page_id' => $landingPageId, 'date' => today()],
        )->increment($field, $amount);
    }

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class);
    }
}
