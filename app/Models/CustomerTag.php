<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Customer-scoped tag — replaces the legacy phone-keyed
 * BookingCustomerTag (dropped in migration 030100).
 */
class CustomerTag extends Model
{
    protected $fillable = [
        'customer_id', 'label', 'color',
        'created_by_type', 'created_by_id',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy()
    {
        return $this->morphTo('created_by');
    }
}
