<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per "visit" — a contiguous burst of user activity ending
 * at the 15-minute idle window or an explicit logout. The middleware
 * extends `last_activity_at` on each authenticated request; a row is
 * considered closed once `ended_at` is set.
 *
 * The "time on platform" totals on the profile screen sum
 * (ended_at ?? last_activity_at) - started_at across these rows, so
 * tabs left open without activity do NOT inflate the total — that's
 * the spec's explicit anti-inflation rule.
 */
class UserVisitSession extends Model
{
    use HasFactory;

    /** Idle window before a visit is treated as ended. */
    public const IDLE_WINDOW_MINUTES = 15;

    protected $fillable = [
        'user_id', 'started_at', 'last_activity_at', 'ended_at',
        'ip', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'started_at'       => 'datetime',
            'last_activity_at' => 'datetime',
            'ended_at'         => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function activityEvents()
    {
        return $this->hasMany(UserActivityEvent::class, 'visit_session_id');
    }

    /**
     * The effective end of the visit — either the explicit logout
     * timestamp or the last activity heartbeat. Drives duration sums.
     */
    public function effectiveEnd(): ?\Illuminate\Support\Carbon
    {
        return $this->ended_at ?? $this->last_activity_at;
    }
}
