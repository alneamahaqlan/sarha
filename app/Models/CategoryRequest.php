<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryRequest extends Model
{
    protected $fillable = [
        'clinic_id', 'service_id', 'name', 'status', 'reviewed_by', 'category_id',
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

    /**
     * Optional — the service the clinic was editing when they submitted
     * the request. When the admin approves, the new category is auto-
     * attached to this service.
     */
    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
