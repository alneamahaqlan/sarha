<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\RateLimiter;

class OtpCode extends Model
{
    /** Wrong-code tries allowed on a single OTP before it is burned. */
    public const MAX_ATTEMPTS = 3;

    protected $fillable = ['phone', 'code', 'purpose', 'is_used', 'attempts', 'expires_at'];

    protected function casts(): array
    {
        return [
            'is_used' => 'boolean',
            'attempts' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function isValid(): bool
    {
        return !$this->is_used && $this->expires_at->isFuture();
    }

    /**
     * Record one wrong-code try. Burns the code (marks it used so it can never
     * be guessed again) once the attempt cap is hit. Returns the number of
     * tries still remaining — 0 means the user must request a fresh code.
     */
    public function registerFailedAttempt(): int
    {
        $this->increment('attempts');

        $remaining = self::MAX_ATTEMPTS - $this->attempts;

        if ($remaining <= 0) {
            $this->update(['is_used' => true]);

            return 0;
        }

        return $remaining;
    }

    /**
     * Per-phone send guard. Defends against bombing a victim's number with
     * OTP SMS across rotating IPs (route-level IP throttling alone can't):
     * a 60-second cooldown between sends, and a hard cap of 5 sends/hour for
     * the same phone+purpose. Returns the seconds the caller must wait when
     * blocked, or null when a send is allowed (recording the attempt).
     */
    public static function throttleSend(string $phone, string $purpose = 'login'): ?int
    {
        $key = "otp-send:{$purpose}:{$phone}";
        $cooldownKey = "{$key}:cooldown";

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return RateLimiter::availableIn($key);
        }
        if (RateLimiter::tooManyAttempts($cooldownKey, 1)) {
            return RateLimiter::availableIn($cooldownKey);
        }

        RateLimiter::hit($key, 3600);       // hourly window
        RateLimiter::hit($cooldownKey, 60); // 60s between sends

        return null;
    }

    public static function generate(string $phone, string $purpose = 'login'): static
    {
        static::where('phone', $phone)->where('purpose', $purpose)->delete();

        return static::create([
            'phone' => $phone,
            'code' => str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes(5),
        ]);
    }
}
