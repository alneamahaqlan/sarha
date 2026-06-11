<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A service a customer expressed interest in (without buying it yet).
 * One row per (customer, service). Drives the interest reports.
 */
class CustomerInterestedService extends Model
{
    protected $fillable = [
        'clinic_id', 'customer_id', 'service_id',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
