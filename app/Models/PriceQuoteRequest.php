<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceQuoteRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id', 'customer_id',
        'user_id', 'customer_name', 'customer_phone',
        'service_name', 'description', 'status', 'clinic_reply',
    ];

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Cities this broadcast request targets — every clinic in them sees it. */
    public function cities()
    {
        return $this->belongsToMany(City::class, 'price_quote_request_city');
    }

    public function replies()
    {
        return $this->hasMany(PriceQuoteReply::class);
    }

    public function publicReplies()
    {
        return $this->hasMany(PriceQuoteReply::class)->where('is_public', true);
    }
}
