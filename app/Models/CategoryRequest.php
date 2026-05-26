<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryRequest extends Model
{
    protected $fillable = [
        'clinic_id', 'name', 'status', 'reviewed_by', 'category_id',
    ];

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
