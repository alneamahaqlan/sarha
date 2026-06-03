<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $fillable = [
        'clinic_id', 'name', 'description', 'price', 'old_price',
        'expires_at', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price'      => 'decimal:2',
            'old_price'  => 'decimal:2',
            'expires_at' => 'date',
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function services()
    {
        return $this->belongsToMany(Service::class)->withPivot('note')->withTimestamps();
    }

    /** Whether the package offer is still valid (active + not expired). */
    public function isLive(): bool
    {
        return $this->is_active && (! $this->expires_at || $this->expires_at->isFuture() || $this->expires_at->isToday());
    }

    public function discountPercentage(): ?int
    {
        if (! $this->old_price || $this->old_price <= $this->price) {
            return null;
        }

        return (int) round((($this->old_price - $this->price) / $this->old_price) * 100);
    }
}
