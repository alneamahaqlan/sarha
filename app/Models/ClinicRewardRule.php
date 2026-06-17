<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A clinic's single auto-grant rule. When `enabled`, every
 * attendance-confirmed booking mints one voucher of the configured shape
 * (see App\Services\RewardService::grantFromAttendance). One row per
 * clinic (unique clinic_id).
 */
class ClinicRewardRule extends Model
{
    protected $fillable = [
        'clinic_id', 'enabled', 'type', 'offer_id', 'service_id',
        'discount_type', 'discount_value', 'validity_days',
    ];

    protected function casts(): array
    {
        return [
            'enabled'        => 'boolean',
            'discount_value' => 'decimal:2',
            'validity_days'  => 'integer',
        ];
    }

    public function clinic(): BelongsTo  { return $this->belongsTo(Clinic::class); }
    public function offer(): BelongsTo    { return $this->belongsTo(Offer::class); }
    public function service(): BelongsTo  { return $this->belongsTo(Service::class); }

    /**
     * Whether this rule is presently usable: enabled, has a type, and the
     * type's target is set. Guards against a half-configured rule minting
     * broken vouchers.
     */
    public function isGrantable(): bool
    {
        if (! $this->enabled || ! $this->type) {
            return false;
        }

        return match ($this->type) {
            RewardVoucher::TYPE_OFFER_DISCOUNT =>
                $this->offer_id !== null
                && in_array($this->discount_type, [RewardVoucher::DISCOUNT_PERCENT, RewardVoucher::DISCOUNT_AMOUNT], true)
                && $this->discount_value !== null && (float) $this->discount_value > 0,
            RewardVoucher::TYPE_FREE_SERVICE => $this->service_id !== null,
            default => false,
        };
    }
}
