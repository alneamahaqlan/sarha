<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomCategory extends Model
{
    protected $fillable = ['clinic_id', 'name', 'emoji', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }
}
