<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    /** Statuses kept here so controllers + UI can pick from one list. */
    public const STATUSES = ['active', 'expired', 'cancelled', 'pending_payment'];

    /** Billing cycle slugs — drives renewal-date math + UI badge. */
    public const CYCLE_QUARTERLY = 'quarterly';
    public const CYCLE_ANNUAL    = 'annual';

    protected $fillable = [
        'clinic_id', 'subscription_package_id', 'type',
        'billing_cycle', 'bonus_months',
        'amount', 'starts_at', 'ends_at',
        'status', 'moyasar_payment_id', 'moyasar_invoice_id',
        'payment_metadata', 'created_by_admin_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'bonus_months' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'payment_metadata' => 'array',
        ];
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function package()
    {
        return $this->belongsTo(SubscriptionPackage::class, 'subscription_package_id');
    }

    public function createdByAdmin()
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    /**
     * How many calendar months this subscription covers — quarterly
     * gets 3, annual gets 12, plus whatever bonus the admin granted.
     */
    public function durationMonths(): int
    {
        return ($this->billing_cycle === self::CYCLE_ANNUAL ? 12 : 3) + (int) $this->bonus_months;
    }
}
