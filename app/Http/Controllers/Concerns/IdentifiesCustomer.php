<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use App\Services\PlatformCustomerResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Shared customer-identity helpers for the public booking + quote flows:
 * a first-time customer verifies via OTP once (which registers them), and a
 * 1-year encrypted cookie lets a returning device pre-fill + skip OTP.
 */
trait IdentifiesCustomer
{
    /** Decode the encrypted "remember me" identity cookie, if present. */
    protected function customerIdentity(Request $request): ?array
    {
        $raw = $request->cookie('booking_identity');
        if (! $raw) {
            return null;
        }
        $data = json_decode($raw, true);

        return (is_array($data) && ! empty($data['phone'])) ? $data : null;
    }

    /** True if a logged-in customer, or a device whose saved phone matches, can skip OTP. */
    protected function customerVerified(Request $request, string $phone): bool
    {
        if (auth('web')->check()) {
            return true;
        }
        $identity = $this->customerIdentity($request);

        return $identity !== null && $identity['phone'] === $phone;
    }

    /** A 1-year cookie so a returning customer is pre-filled and skips OTP. */
    protected function identityCookie(string $name, string $phone): Cookie
    {
        return cookie('booking_identity', json_encode(['name' => $name, 'phone' => $phone]), 60 * 24 * 365);
    }

    /** Find or register the customer user (no password — OTP-based). */
    protected function resolveCustomerUser(string $name, string $phone): User
    {
        $user = User::firstOrCreate(
            ['phone' => $phone],
            ['name' => $name, 'is_active' => true],
        );

        // The customer just proved this phone via OTP — adopt their name
        // as the canonical platform identity and mark the phone verified,
        // unifying any clinic-entered records under the same person.
        app(PlatformCustomerResolver::class)->attachUser($user);

        return $user;
    }
}
