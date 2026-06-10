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

    /** Saved services + offers (polymorphic). */
    public function savedItems()
    {
        return $this->hasMany(SavedItem::class);
    }

    /** Memoised "Type:id" set of the user's saved items — avoids N+1 in card lists. */
    protected ?\Illuminate\Support\Collection $savedKeyCache = null;

    public function savedKeySet(): \Illuminate\Support\Collection
    {
        return $this->savedKeyCache ??= $this->savedItems()
            ->get(['favoritable_type', 'favoritable_id'])
            ->map(fn ($s) => $s->favoritable_type . ':' . $s->favoritable_id);
    }

    public function hasSaved(\Illuminate\Database\Eloquent\Model $model): bool
    {
        return $this->savedKeySet()->contains($model->getMorphClass() . ':' . $model->getKey());
    }

    /** Cart items — polymorphic to Service / Offer / Package. */
    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    /** Abandoned-cart outreach this user received from clinics. */
    public function cartContacts()
    {
        return $this->hasMany(CartContact::class);
    }

    public function cartCount(): int
    {
        return $this->cartItems()->count();
    }

    /** Memoised "Type:id" set of the user's cart — avoids N+1 in card lists. */
    protected ?\Illuminate\Support\Collection $cartKeyCache = null;

    public function cartKeySet(): \Illuminate\Support\Collection
    {
        return $this->cartKeyCache ??= $this->cartItems()
            ->get(['cartable_type', 'cartable_id'])
            ->map(fn ($c) => $c->cartable_type . ':' . $c->cartable_id);
    }

    public function hasInCart(\Illuminate\Database\Eloquent\Model $model): bool
    {
        return $this->cartKeySet()->contains($model->getMorphClass() . ':' . $model->getKey());
    }
}
