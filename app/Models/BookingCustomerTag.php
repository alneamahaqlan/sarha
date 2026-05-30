<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingCustomerTag extends Model
{
    protected $fillable = [
        'clinic_id', 'customer_phone', 'label', 'color',
        'created_by_type', 'created_by_id',
    ];

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function createdBy()
    {
        return $this->morphTo('created_by');
    }
}
