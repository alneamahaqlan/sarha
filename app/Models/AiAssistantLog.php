<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Single turn of a conversation with the public AI assistant.
 *
 * Written by AiAssistantInterceptor after the underlying AiAssistantService
 * has returned. Read (in Phase 2) by the admin's monitoring screens — for
 * Phase 1 nothing reads back from this table, by design.
 */
class AiAssistantLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'visitor_id', 'guard', 'conversation_id',
        'query', 'reply', 'kind',
        'provider', 'model', 'tokens_in', 'tokens_out', 'response_ms', 'locale',
        'blocked_by_restriction_id', 'was_emergency', 'was_blocked',
        'stats_bumped',
    ];

    protected $casts = [
        'was_emergency' => 'boolean',
        'was_blocked'   => 'boolean',
        'stats_bumped'  => 'boolean',
        'tokens_in'     => 'integer',
        'tokens_out'    => 'integer',
        'response_ms'   => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Many-to-many to clinics that surfaced in this reply. Drives the
     * "this clinic showed up in N AI conversations" stat that Phase 2
     * will expose. Pivot only has created_at — the row is immutable, so
     * no updated_at and no withTimestamps().
     */
    public function clinics(): BelongsToMany
    {
        return $this->belongsToMany(Clinic::class, 'ai_assistant_log_clinics', 'log_id', 'clinic_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'ai_assistant_log_categories', 'log_id', 'category_id');
    }
}
