<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Generic key/value config the super-admin can edit at
 * /app/admin/system-settings. Values are cast on read by `type`. The
 * `encrypted` type round-trips through Crypt — used to hide API keys
 * (Gemini / OpenAI / Anthropic) at rest.
 */
class SystemSetting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'group', 'label', 'description'];

    /** Placeholder the API returns instead of leaking encrypted values. */
    public const MASK = '••••••••';

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("setting:{$key}", 3600, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();
            return $setting ? $setting->castValue() : $default;
        });
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("setting:{$key}");
    }

    public function castValue(): mixed
    {
        return match ($this->type) {
            'boolean' => (bool) $this->value,
            'integer' => (int) $this->value,
            'float', 'decimal' => (float) $this->value,
            'json', 'array' => json_decode($this->value ?? 'null', true),
            'encrypted' => $this->decryptedValue(),
            default => $this->value,
        };
    }

    /** True when an encrypted slot has a stored ciphertext. */
    public function hasEncryptedValue(): bool
    {
        return $this->type === 'encrypted' && is_string($this->value) && $this->value !== '';
    }

    /** Decrypt the stored ciphertext; returns null on corruption / empty. */
    private function decryptedValue(): ?string
    {
        if (! is_string($this->value) || $this->value === '') {
            return null;
        }
        try {
            return Crypt::decryptString($this->value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Encrypt plaintext API keys on save. Idempotent — if the value is
     * already an APP_KEY-encrypted string, we leave it alone (this lets
     * raw seeders and re-saves co-exist).
     */
    protected static function booted(): void
    {
        static::saving(function (self $setting) {
            if ($setting->type !== 'encrypted') return;
            if (! $setting->isDirty('value')) return;
            if (! is_string($setting->value) || $setting->value === '') return;

            try {
                Crypt::decryptString($setting->value); // already encrypted → no-op
            } catch (Throwable) {
                $setting->value = Crypt::encryptString($setting->value);
            }
        });
    }
}
