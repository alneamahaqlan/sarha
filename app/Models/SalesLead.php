<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesLead extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'clinic_name', 'contact_name', 'phone', 'email', 'city_id',
        'status', 'notes', 'assigned_to', 'next_follow_up_at',
    ];

    protected function casts(): array
    {
        return [
            'next_follow_up_at' => 'datetime',
        ];
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function assignedAdmin()
    {
        return $this->belongsTo(Admin::class, 'assigned_to');
    }
}
