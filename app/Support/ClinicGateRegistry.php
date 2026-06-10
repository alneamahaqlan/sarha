<?php

namespace App\Support;

/**
 * Single source of truth for per-clinic feature gates that follow the standard
 * state machine: active | disabled | pending | rejected, backed by columns on
 * the `clinics` table (status / rejection_reason / requested_at / reviewed_by).
 *
 * The admin "Access Center" reads this registry to build its inbox, the
 * per-clinic view and the approve/reject/disable actions generically — so
 * ADDING A NEW GATE LATER is a single entry here, with NO controller or UI
 * changes. The clinic-side request endpoint just flips its own status column
 * to 'pending'; everything else is driven from this list.
 */
class ClinicGateRegistry
{
    /** @return array<string, array{label_ar:string,label_en:string,status:string,reason:string,requested:string,reviewed:string,default:string}> */
    public static function gates(): array
    {
        return [
            'cart' => [
                'label_ar'  => 'السلة',
                'label_en'  => 'Cart',
                'status'    => 'cart_status',
                'reason'    => 'cart_rejection_reason',
                'requested' => 'cart_requested_at',
                'reviewed'  => 'cart_reviewed_by',
                'default'   => 'disabled',
            ],
            'tracking' => [
                'label_ar'  => 'بكسلات التتبع والتسويق',
                'label_en'  => 'Tracking pixels',
                'status'    => 'tracking_status',
                'reason'    => 'tracking_rejection_reason',
                'requested' => 'tracking_requested_at',
                'reviewed'  => 'tracking_reviewed_by',
                'default'   => 'disabled',
            ],
            'price_quote' => [
                'label_ar'  => 'الرد على طلبات التسعير',
                'label_en'  => 'Price-quote replies',
                'status'    => 'price_quote_status',
                'reason'    => 'price_quote_rejection_reason',
                'requested' => 'price_quote_requested_at',
                'reviewed'  => 'price_quote_reviewed_by',
                'default'   => 'active',
            ],
        ];
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::gates());
    }

    public static function get(string $key): ?array
    {
        return self::gates()[$key] ?? null;
    }
}
