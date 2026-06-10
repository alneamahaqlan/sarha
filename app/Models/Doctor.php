<?php

namespace App\Models;

use App\Models\Concerns\HasDemoData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Doctor extends Model
{
    use HasDemoData;

    protected $fillable = [
        'clinic_id', 'sub_clinic_id', 'name', 'specialty', 'gender', 'photo',
        'bio', 'qualifications', 'years_experience', 'university', 'languages',
        'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active'        => 'boolean',
            'years_experience' => 'integer',
            'sort_order'       => 'integer',
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

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo ? Storage::url($this->photo) : null;
    }
}
