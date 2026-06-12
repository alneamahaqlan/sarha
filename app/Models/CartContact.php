<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One outreach event from a clinic to a customer about their abandoned
 * cart. The latest row per (clinic_id, user_id) answers "did the clinic
 * follow up, and how recently?" — surfaced as the "contacted in 3 days"
 * flag on both the admin and clinic abandoned-cart views.
 */
class CartContact extends Model
{
    public const CHANNELS = ['whatsapp', 'call', 'manual'];

    protected $fillable = [
        'clinic_id', 'user_id', 'channel', 'note',
        'created_by_type', 'created_by_id', 'created_by_name',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
