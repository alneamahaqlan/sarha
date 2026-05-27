<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Admin-managed service category. Every Service belongs to exactly one
 * ServiceCategory — a complex's "add service" form forces a pick from
 * this lookup before the row can be saved.
 *
 * Distinct from {@see Category}: that one groups clinics by specialty
 * (e.g. "Dentistry" means "this is a dental complex"); this one groups
 * services by procedure type (e.g. "ليزر إزالة شعر", "زراعة أسنان").
 */
class ServiceCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'name_en', 'slug', 'icon', 'emoji',
        'description', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /**
     * Locale-aware label — Arabic by default, English when the app is in
     * 'en' (mirrors Category::getDisplayNameAttribute so callers can use
     * either lookup interchangeably).
     */
    public function getDisplayNameAttribute(): string
    {
        return app()->getLocale() === 'en' && filled($this->name_en)
            ? $this->name_en
            : (string) $this->name;
    }
}
