<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id', 'type', 'amount', 'starts_at', 'ends_at',
        'status', 'moyasar_payment_id', 'moyasar_invoice_id',
        'payment_metadata', 'created_by_admin_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'payment_metadata' => 'array',
        ];
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function createdByAdmin()
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }
}
