<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One anonymous visitor session on a landing page. Carries UTM attribution +
 * engagement metrics; flipped to converted (with the booking) on submit.
 */
class LandingPageVisit extends Model
{
    protected $fillable = [
        'landing_page_id', 'visitor_id', 'session_id', 'user_id',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
        'referrer', 'landing_url', 'user_agent', 'device', 'ip_hash',
        'started_at', 'last_activity_at', 'ended_at',
        'scroll_depth', 'duration_seconds', 'is_bounce',
        'converted', 'converted_at', 'booking_id',
    ];

    protected function casts(): array
    {
        return [
            'started_at'       => 'datetime',
            'last_activity_at' => 'datetime',
            'ended_at'         => 'datetime',
            'converted_at'     => 'datetime',
            'scroll_depth'     => 'integer',
            'duration_seconds' => 'integer',
            'is_bounce'        => 'boolean',
            'converted'        => 'boolean',
        ];
    }

    /** UTM tags as a compact array, dropping the empty ones. */
    public function utm(): array
    {
        return array_filter([
            'utm_source'   => $this->utm_source,
            'utm_medium'   => $this->utm_medium,
            'utm_campaign' => $this->utm_campaign,
            'utm_content'  => $this->utm_content,
            'utm_term'     => $this->utm_term,
        ], fn ($v) => filled($v));
    }

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(LandingPageEvent::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
