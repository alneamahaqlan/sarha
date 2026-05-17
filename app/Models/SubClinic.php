<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubClinic extends Model
{
    protected $fillable = [
        'clinic_id', 'category_id', 'name', 'name_en',
        'description', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getDisplayNameAttribute(): string
    {
        return app()->getLocale() === 'en' && filled($this->name_en)
            ? $this->name_en
            : (string) $this->name;
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function services()
    {
        return $this->hasMany(Service::class)->where('is_active', true)->orderBy('sort_order');
    }
}
