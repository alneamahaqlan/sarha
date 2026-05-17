<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'clinic_id', 'custom_category_id', 'sub_clinic_id',
        'name', 'description',
        'price', 'old_price', 'offer_expires_at', 'image',
        'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'old_price' => 'decimal:2',
            'is_active' => 'boolean',
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

    public function customCategory()
    {
        return $this->belongsTo(CustomCategory::class);
    }

    public function subClinic()
    {
        return $this->belongsTo(SubClinic::class);
    }
}
