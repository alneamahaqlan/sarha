<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    protected $fillable = ['phone', 'code', 'purpose', 'is_used', 'expires_at'];

    protected function casts(): array
    {
        return [
            'is_used' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function isValid(): bool
    {
        return !$this->is_used && $this->expires_at->isFuture();
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
