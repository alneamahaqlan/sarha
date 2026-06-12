<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerNameAlias;
use App\Models\PlatformCustomer;
use App\Support\PhoneNormalizer;
use Illuminate\Http\JsonResponse;

/**
 * Admin-only view of a customer's unified identity: the primary
 * (self-registered) name plus every alternative name, each labeled with
 * the clinic that supplied it. Unlike the clinic view, the admin sees
 * the WHOLE picture across all clinics.
 */
class PlatformCustomerController extends Controller
{
    public function showByPhone(string $phone): JsonResponse
    {
        $normalized = PhoneNormalizer::normalizeOrSelf($phone);

        $platform = PlatformCustomer::query()
            ->where('phone', $normalized)
            ->with(['aliases.clinic:id,name'])
            ->first();

        if (! $platform) {
            return response()->json(['data' => [
                'phone'        => $normalized,
                'primary_name' => '—',
                'is_verified'  => false,
                'self_name'    => null,
                'alternatives' => [],
            ]]);
        }

        $primary = $platform->displayName();

        // Everything that isn't the primary becomes an "alternative",
        // tagged with its origin clinic (or "self" for the customer's own).
        $alternatives = $platform->aliases
            ->sortBy('created_at')
            ->reject(fn (CustomerNameAlias $a) => $a->name === $primary && $a->source === CustomerNameAlias::SOURCE_SELF)
            ->map(fn (CustomerNameAlias $a) => [
                'name'        => $a->name,
                'source'      => $a->source,
                'clinic_id'   => $a->clinic_id,
                'clinic_name' => $a->clinic?->name,
            ])
            ->values()
            ->all();

        $selfName = $platform->aliases
            ->firstWhere('source', CustomerNameAlias::SOURCE_SELF)?->name;

        return response()->json(['data' => [
            'phone'        => $platform->phone,
            'primary_name' => $primary,
            'is_verified'  => (bool) $platform->is_phone_verified,
            'self_name'    => $selfName,
            'alternatives' => $alternatives,
        ]]);
    }
}
