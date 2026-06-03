<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A WhatsApp number registered with the gateway (Wappi) for delivering OTP
 * codes. The token is encrypted at rest via the `encrypted` cast. Health
 * fields (last_used_at / last_failed_at / failure_count) drive the rotation
 * + circuit-breaker logic in {@see \App\Services\Messaging\Selectors\LeastRecentlyUsedSenderSelector}.
 */
class WhatsAppSender extends Model
{
    /** Eloquent would derive `whats_app_senders` from the StudlyCase name. */
    protected $table = 'whatsapp_senders';

    /** Hard cap on how many numbers the panel allows. */
    public const MAX_SENDERS = 5;

    /** Consecutive failures before a sender is temporarily benched. */
    public const FAILURE_THRESHOLD = 5;

    /** Minutes a benched sender stays out of rotation after its last failure. */
    public const COOLDOWN_MINUTES = 10;

    protected $fillable = [
        'label',
        'phone',
        'provider',
        'profile_id',
        'token',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'priority'       => 'integer',
        'failure_count'  => 'integer',
        'token'          => 'encrypted',
        'last_used_at'   => 'datetime',
        'last_failed_at' => 'datetime',
    ];

    /** Only senders carrying both a profile id and a token can actually send. */
    public function hasCredentials(): bool
    {
        return filled($this->profile_id) && filled($this->token);
    }

    /* --------------------------------------------------------------------- */
    /* Health tracking                                                       */
    /* --------------------------------------------------------------------- */

    public function recordSuccess(): void
    {
        $this->forceFill([
            'last_used_at'   => now(),
            'last_failed_at' => null,
            'failure_count'  => 0,
        ])->save();
    }

    public function recordFailure(): void
    {
        $this->forceFill([
            'last_used_at'   => now(),
            'last_failed_at' => now(),
            'failure_count'  => $this->failure_count + 1,
        ])->save();
    }

    /* --------------------------------------------------------------------- */
    /* Scopes                                                                */
    /* --------------------------------------------------------------------- */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeWithCredentials(Builder $query): Builder
    {
        return $query->whereNotNull('profile_id')
            ->whereNotNull('token')
            ->where('token', '!=', '');
    }

    /**
     * Drop senders that have tripped the circuit breaker — too many recent
     * failures and still inside their cooldown window.
     */
    public function scopeHealthy(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('failure_count', '<', self::FAILURE_THRESHOLD)
              ->orWhere('last_failed_at', '<=', now()->subMinutes(self::COOLDOWN_MINUTES));
        });
    }
}
