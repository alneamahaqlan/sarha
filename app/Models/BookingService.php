<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single service line item taken on a booking (Kanban card). Many per
 * booking; each carries its own net_price (catalog price minus any
 * per-customer discount). See the create_booking_services migration.
 */
class BookingService extends Model
{
    protected $fillable = [
        'clinic_id', 'booking_id', 'service_id', 'list_price', 'net_price',
    ];

    protected function casts(): array
    {
        return [
            'list_price' => 'decimal:2',
            'net_price'  => 'decimal:2',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
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
