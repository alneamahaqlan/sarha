<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AI-generated summary of one user's chat thread. Read by the
 * super-admin's user profile page; never shown to the user themselves
 * (Phase 2 policy — see the brief for the privacy decision).
 */
class AiUserInteractionSummary extends Model
{
    use HasFactory;

    public const SERIOUSNESS = ['exploration', 'comparing', 'near_decision'];

    protected $fillable = [
        'user_id', 'log_id', 'topic',
        'categories', 'clinics',
        'seriousness', 'model_used', 'generated_at',
    ];

    protected $casts = [
        'categories'   => 'array',
        'clinics'      => 'array',
        'generated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function log(): BelongsTo
    {
        return $this->belongsTo(AiAssistantLog::class, 'log_id');
    }
}
