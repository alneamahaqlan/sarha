<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'clinic_id', 'sub_clinic_id',
        'name', 'description',
        'price', 'old_price', 'offer_expires_at', 'is_featured_offer', 'image',
        'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'old_price' => 'decimal:2',
            'is_active' => 'boolean',
            'is_featured_offer' => 'boolean',
            'offer_expires_at' => 'datetime',
        ];
    }

    public function hasActiveOffer(): bool
    {
        return $this->old_price && $this->offer_expires_at?->isFuture();
    }

    public function discountPercentage(): int
    {
        if (!$this->hasActiveOffer()) {
            return 0;
        }
        return (int) round((($this->old_price - $this->price) / $this->old_price) * 100);
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function subClinic()
    {
        return $this->belongsTo(SubClinic::class);
    }

    /**
     * The specialties this service belongs to. Many-to-many because a single
     * service often spans more than one specialty (e.g. "laser hair removal"
     * is both dermatology + cosmetics). Validated to 1–5 categories at the
     * API layer.
     */
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_service')->withTimestamps();
    }

    public function packages()
    {
        return $this->belongsToMany(Package::class)->withTimestamps();
    }
}
