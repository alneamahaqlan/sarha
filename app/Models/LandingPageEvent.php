<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only behavioral event for a landing-page visit. No updated_at — rows
 * are never mutated. `payload` only holds allow-listed, non-health keys.
 */
class LandingPageEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'landing_page_visit_id', 'landing_page_id', 'type', 'payload', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'payload'     => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(LandingPageVisit::class, 'landing_page_visit_id');
    }

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class);
    }
}
