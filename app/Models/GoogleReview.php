<?php

namespace App\Models;

use App\Models\Concerns\HasDemoData;
use Illuminate\Database\Eloquent\Model;

class GoogleReview extends Model
{
    use HasDemoData;

    protected $fillable = [
        'clinic_id', 'reviewer_name', 'reviewer_photo', 'rating',
        'review_text', 'google_review_id', 'reviewed_at', 'is_visible',
    ];

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }
}
