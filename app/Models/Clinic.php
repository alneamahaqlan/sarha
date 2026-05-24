<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class Clinic extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'phone', 'email', 'license_number', 'password', 'city_id',
        'address', 'district', 'latitude', 'longitude', 'google_place_id',
        'description', 'logo', 'gallery', 'website', 'instagram',
        'twitter', 'snapchat', 'status', 'subscription_type',
        'subscription_starts_at', 'subscription_ends_at',
        'rejection_reason', 'is_featured', 'sort_order',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'gallery' => 'array',
            'is_featured' => 'boolean',
            'subscription_starts_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Clinic $clinic) {
            if (empty($clinic->slug)) {
                $clinic->slug = Str::slug($clinic->name);
            }
        });
    }

    public function isSubscriptionActive(): bool
    {
        return $this->status === 'active'
            && $this->subscription_ends_at
            && $this->subscription_ends_at->isFuture();
    }

    public function isPremium(): bool
    {
        return $this->subscription_type === 'premium' && $this->isSubscriptionActive();
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->whereNotNull('subscription_ends_at')
            ->where('subscription_ends_at', '>=', now());
    }

    public function scopeRankedForListing(Builder $query): Builder
    {
        return $query
            ->orderByDesc('is_featured')
            ->orderByRaw("CASE WHEN subscription_type = 'premium' THEN 0 ELSE 1 END")
            ->latest('created_at');
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'clinic_categories');
    }

    public function customCategories()
    {
        return $this->hasMany(CustomCategory::class);
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function articles()
    {
        return $this->hasMany(Article::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function priceQuoteRequests()
    {
        return $this->hasMany(PriceQuoteRequest::class);
    }

    public function googleReviews()
    {
        return $this->hasMany(GoogleReview::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function stats()
    {
        return $this->hasMany(ClinicStat::class);
    }

    public function aiConversations()
    {
        return $this->hasMany(AiConversation::class);
    }

    public function notifications()
    {
        return $this->morphMany(PlatformNotification::class, 'notifiable');
    }

    public function workingHours()
    {
        return $this->hasMany(ClinicWorkingHour::class)->orderBy('day_of_week');
    }

    public function complaints()
    {
        return $this->hasMany(Complaint::class);
    }

    public function subClinics()
    {
        return $this->hasMany(SubClinic::class)->where('is_active', true)->orderBy('sort_order');
    }

    public function whatsappLink(?string $message = null): string
    {
        $phone = preg_replace('/\D/', '', $this->phone ?? '');
        if (str_starts_with($phone, '05')) {
            $phone = '966' . substr($phone, 1);
        }
        $url = 'https://wa.me/' . $phone;
        if ($message) {
            $url .= '?text=' . urlencode($message);
        }
        return $url;
    }
}
