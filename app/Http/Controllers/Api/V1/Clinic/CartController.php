<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Clinic-side cart feature management. The clinic requests activation; a
 * super-admin approves before the cart appears on the public storefront.
 * Once active the clinic can show/hide the cart on its own pages via
 * `cart_storefront_enabled`. Auto-scoped to auth('clinic').
 */
class CartController extends Controller
{
    public function show(): JsonResponse
    {
        $clinic = auth('clinic')->user();

        return response()->json([
            'data' => [
                'cart_status'             => $clinic->cart_status,
                'cart_rejection_reason'   => $clinic->cart_rejection_reason,
                'cart_requested_at'       => $clinic->cart_requested_at,
                'cart_storefront_enabled' => (bool) $clinic->cart_storefront_enabled,
            ],
        ]);
    }

    /** Toggle the public storefront show/hide switch (only meaningful while active). */
    public function update(Request $request): JsonResponse
    {
        $clinic = auth('clinic')->user();

        $data = $request->validate([
            'cart_storefront_enabled' => ['required', 'boolean'],
        ]);

        $clinic->update(['cart_storefront_enabled' => $data['cart_storefront_enabled']]);

        return $this->show();
    }

    /** Submit the cart feature for super-admin approval. */
    public function requestActivation(): JsonResponse
    {
        $clinic = auth('clinic')->user();

        // Only meaningful from a non-active state.
        if (in_array($clinic->cart_status, ['disabled', 'rejected'], true)) {
            $clinic->update([
                'cart_status'           => 'pending',
                'cart_rejection_reason' => null,
                'cart_requested_at'     => now(),
            ]);
        }

        return $this->show();
    }
}
