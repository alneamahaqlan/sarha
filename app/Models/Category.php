<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name', 'name_en', 'emoji', 'slug', 'icon', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function clinics()
    {
        return $this->belongsToMany(Clinic::class, 'clinic_categories');
    }

    public function getDisplayNameAttribute(): string
    {
        return app()->getLocale() === 'en' && filled($this->name_en)
            ? $this->name_en
            : (string) $this->name;
    }
}
