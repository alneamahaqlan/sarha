<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceQuoteRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id', 'user_id', 'customer_name', 'customer_phone',
        'service_name', 'description', 'status', 'clinic_reply',
    ];

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
