<?php

namespace App\Http\Controllers\Concerns;

use App\Models\CartItem;
use App\Models\Offer;
use App\Models\Package;
use App\Models\Service;

/**
 * Shared presentation for cart rows on the admin + clinic abandoned-cart
 * surfaces: a stable {type,label} for the polymorphic target plus a single
 * row shape. Targets are loaded `withTrashed()` so a deleted service still
 * renders (labelled deleted) instead of vanishing from the count.
 */
trait PresentsCartItems
{
    /** FQCN morph type → short, UI-friendly token. */
    private function cartableType(string $class): string
    {
        return match ($class) {
            Service::class => 'service',
            Offer::class   => 'offer',
            Package::class => 'package',
            default        => 'item',
        };
    }

    /** One abandoned-cart item as a flat array for JSON. */
    private function cartItemRow(CartItem $item): array
    {
        $cartable = $item->cartable;
        $deleted  = ! $cartable || (method_exists($cartable, 'trashed') && $cartable->trashed());

        return [
            'id'         => $item->id,
            'type'       => $this->cartableType($item->cartable_type),
            'name'       => $cartable->name ?? $cartable->title ?? '—',
            'price'      => isset($cartable->price) ? (float) $cartable->price : null,
            'deleted'    => $deleted,
            'added_at'   => $item->created_at?->toIso8601String(),
            'booked_at'  => $item->booked_at?->toIso8601String(),
        ];
    }
}
