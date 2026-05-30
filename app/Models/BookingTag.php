<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingTag extends Model
{
    protected $fillable = [
        'booking_id', 'label', 'color',
        'created_by_type', 'created_by_id',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function createdBy()
    {
        return $this->morphTo('created_by');
    }
}
