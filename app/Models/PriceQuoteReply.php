<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceQuoteReply extends Model
{
    protected $fillable = [
        'price_quote_request_id', 'clinic_id', 'body', 'price', 'is_public',
    ];

    protected function casts(): array
    {
        return [
            'price'     => 'decimal:2',
            'is_public' => 'boolean',
        ];
    }

    public function request()
    {
        return $this->belongsTo(PriceQuoteRequest::class, 'price_quote_request_id');
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }
}
