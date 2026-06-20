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

    /** Display badges (Badges Center) attached to this doctor. */
    public function badges()
    {
        return $this->morphToMany(Badge::class, 'badgeable')
            ->withPivot(['source', 'expires_at'])
            ->withTimestamps();
    }

    public function subClinic()
    {
        return $this->belongsTo(SubClinic::class);
    }

    /**
     * Services this doctor provides (many-to-many). Surfaced on the doctor's
     * public profile and selected on the clinic "add service" form.
     */
    public function services()
    {
        return $this->belongsToMany(Service::class, 'doctor_service')->withTimestamps();
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo ? Storage::url($this->photo) : null;
    }
}
