<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A single-use cashback voucher — NOT money. Owned by a person (the
 * platform-wide identity, by phone), locked to the issuing clinic, and
 * redeemable against a specific offer (discount) or service (free).
 *
 * Lifecycle: active → used (redeemed) | expired (past expires_at) | void
 * (e.g. attendance that minted it was revoked before use). See
 * App\Services\RewardService for all transitions.
 */
class RewardVoucher extends Model
{
    public const TYPE_OFFER_DISCOUNT = 'offer_discount';
    public const TYPE_FREE_SERVICE   = 'free_service';

    public const STATUS_ACTIVE  = 'active';
    public const STATUS_USED    = 'used';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_VOID    = 'void';

    public const SOURCE_ATTENDANCE = 'attendance';
    public const SOURCE_MANUAL     = 'manual';

    public const DISCOUNT_PERCENT = 'percent';
    public const DISCOUNT_AMOUNT  = 'amount';

    public const CODE_PREFIX = 'RWD-';

    protected $fillable = [
        'clinic_id', 'platform_customer_id', 'customer_id', 'phone',
        'type', 'offer_id', 'service_id', 'discount_type', 'discount_value',
        'status', 'source', 'origin_booking_id', 'redeemed_booking_id', 'applied_booking_id',
        'code', 'expires_at', 'used_at', 'expiry_reminded_at', 'reactivation_reminded_at',
        'granted_by_type', 'granted_by_id', 'granted_by_name',
        'transferred_at', 'transferred_from_phone',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'expires_at'               => 'datetime',
            'used_at'                  => 'datetime',
            'expiry_reminded_at'       => 'datetime',
            'reactivation_reminded_at' => 'datetime',
            'transferred_at'           => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (RewardVoucher $voucher) {
            if (empty($voucher->code)) {
                $voucher->code = static::generateCode();
            }
        });
    }

    public static function generateCode(): string
    {
        do {
            $code = self::CODE_PREFIX . strtoupper(Str::random(6));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    // ---------- relations ----------
    public function clinic(): BelongsTo            { return $this->belongsTo(Clinic::class); }
    public function platformCustomer(): BelongsTo  { return $this->belongsTo(PlatformCustomer::class); }
    public function customer(): BelongsTo           { return $this->belongsTo(Customer::class); }
    public function offer(): BelongsTo              { return $this->belongsTo(Offer::class); }
    public function service(): BelongsTo            { return $this->belongsTo(Service::class); }
    public function originBooking(): BelongsTo      { return $this->belongsTo(Booking::class, 'origin_booking_id'); }
    public function redeemedBooking(): BelongsTo    { return $this->belongsTo(Booking::class, 'redeemed_booking_id'); }
    public function appliedBooking(): BelongsTo     { return $this->belongsTo(Booking::class, 'applied_booking_id'); }

    /** Who granted it manually — Clinic owner or ClinicTeamMember. */
    public function grantedBy()
    {
        return $this->morphTo('granted_by');
    }

    /**
     * Localized one-line description of what this voucher grants — shared
     * by the account rewards page and the booking-form picker so the
     * wording stays consistent.
     */
    public function describe(): string
    {
        if ($this->type === self::TYPE_FREE_SERVICE) {
            return __('site.reward_desc_free_service', ['service' => $this->service?->name ?? '—']);
        }

        $offer = $this->offer?->title ?? '—';
        $value = rtrim(rtrim(number_format((float) $this->discount_value, 2, '.', ''), '0'), '.');
        return $this->discount_type === self::DISCOUNT_PERCENT
            ? __('site.reward_desc_offer_percent', ['value' => $value, 'offer' => $offer])
            : __('site.reward_desc_offer_amount', ['value' => $value, 'offer' => $offer]);
    }

    // ---------- state helpers ----------
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && ! $this->isExpired();
    }

    /** True when an active voucher has passed its expiry instant. */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    // ---------- scopes ----------
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForPlatformCustomer(Builder $q, int $platformCustomerId): Builder
    {
        return $q->where('platform_customer_id', $platformCustomerId);
    }
}
