<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceQuoteReply extends Model
{
    protected $fillable = [
        'price_quote_request_id', 'clinic_id', 'body', 'price', 'is_public',
        // Member attribution (Phase: team members) — captured at reply
        // time so soft-deleting the member doesn't blank the customer view.
        'replied_by_member_id',
        'replied_by_name_snapshot',
        'replied_by_role_snapshot',
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

    public function repliedByMember()
    {
        return $this->belongsTo(ClinicTeamMember::class, 'replied_by_member_id');
    }
}
