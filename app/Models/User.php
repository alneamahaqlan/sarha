<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'otp_code',
        'otp_expires_at',
        'is_active',
    ];

    protected $hidden = [
        'otp_code',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'otp_expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Bookings the user placed on behalf of one of their saved relatives,
     * including the ones they made for themselves. Same source as
     * `bookings()` — exposed separately so the account-page filters can
     * scope on it without renaming the primary relation.
     */
    public function bookerBookings()
    {
        return $this->hasMany(Booking::class, 'booker_user_id');
    }

    public function relatives()
    {
        return $this->hasMany(Relative::class);
    }

    public function priceQuoteRequests()
    {
        return $this->hasMany(PriceQuoteRequest::class);
    }

    public function notifications()
    {
        return $this->morphMany(PlatformNotification::class, 'notifiable');
    }

    public function favorites()
    {
        return $this->belongsToMany(Clinic::class, 'favorites')->withTimestamps();
    }

    public function hasFavorited(Clinic $clinic): bool
    {
        return $this->favorites()->where('clinics.id', $clinic->id)->exists();
    }
}
