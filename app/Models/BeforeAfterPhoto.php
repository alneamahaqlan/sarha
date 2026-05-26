<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class BeforeAfterPhoto extends Model
{
    protected $fillable = [
        'clinic_id', 'sub_clinic_id', 'service_id',
        'title', 'before_image', 'after_image', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function subClinic()
    {
        return $this->belongsTo(SubClinic::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function getBeforeUrlAttribute(): ?string
    {
        return $this->before_image ? Storage::url($this->before_image) : null;
    }

    public function getAfterUrlAttribute(): ?string
    {
        return $this->after_image ? Storage::url($this->after_image) : null;
    }
}
