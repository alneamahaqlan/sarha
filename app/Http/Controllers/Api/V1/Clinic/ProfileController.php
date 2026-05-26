<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clinic\UpdateProfileRequest;
use App\Http\Resources\Api\V1\ClinicResource as ClinicApiResource;
use App\Models\SystemSetting;
use App\Services\GoogleMapsService;
use App\Services\GooglePlacesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function show(): ClinicApiResource
    {
        $clinic = auth('clinic')->user()->load('city:id,name');

        return new ClinicApiResource($clinic);
    }

    public function update(UpdateProfileRequest $request): ClinicApiResource
    {
        $clinic = auth('clinic')->user();
        $data = $request->validated();

        // Mirror ClinicProfile page: empty password → keep existing; otherwise bcrypt.
        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $clinic->update($data);

        return new ClinicApiResource($clinic->fresh()->load('city:id,name'));
    }

    /**
     * Parse a Google Maps URL into lat/lng. Does NOT persist — the form
     * saves them through the regular profile PATCH.
     */
    public function extractCoords(Request $request, GoogleMapsService $maps): JsonResponse
    {
        $validated = $request->validate(['url' => ['required', 'string']]);

        $coords = $maps->extractCoordinatesFromUrl($validated['url']);

        if (! $coords) {
            return response()->json(['message' => __('Could not extract coordinates from this URL.')], 422);
        }

        return response()->json([
            'data' => [
                'latitude'  => $coords['lat'],
                'longitude' => $coords['lng'],
            ],
        ]);
    }

    /**
     * Fetch Google reviews for the authenticated clinic.
     */
    public function syncReviews(GooglePlacesService $places): JsonResponse
    {
        $clinic = auth('clinic')->user();

        if (! $clinic->google_place_id) {
            return response()->json(['message' => __('Set a Google Place ID first.')], 422);
        }

        $result = $places->syncReviews($clinic);

        if (! $result['success']) {
            return response()->json(['message' => $result['message'] ?? __('Failed to sync reviews.')], 422);
        }

        return response()->json(['data' => ['fetched' => $result['fetched']]]);
    }

    /**
     * Synced Google reviews for the authenticated clinic (read-only display in
     * the clinic panel). Data is populated by GooglePlacesService::syncReviews.
     */
    public function reviews(): JsonResponse
    {
        $clinic = auth('clinic')->user();
        $base = $clinic->googleReviews()->where('is_visible', true);

        $count = (clone $base)->count();
        $average = $count > 0 ? round((clone $base)->avg('rating'), 1) : null;

        $reviews = (clone $base)
            ->latest('reviewed_at')
            ->limit(20)
            ->get(['id', 'reviewer_name', 'reviewer_photo', 'rating', 'review_text', 'reviewed_at'])
            ->map(fn ($r) => [
                'id'             => $r->id,
                'reviewer_name'  => $r->reviewer_name,
                'reviewer_photo' => $r->reviewer_photo,
                'rating'         => (int) $r->rating,
                'review_text'    => $r->review_text,
                'reviewed_at'    => $r->reviewed_at?->toIso8601String(),
            ]);

        return response()->json(['data' => [
            'average' => $average,
            'count'   => $count,
            'reviews' => $reviews,
        ]]);
    }

    /**
     * Current subscription state + platform plan prices.
     */
    public function subscription(): JsonResponse
    {
        $clinic = auth('clinic')->user();

        $endsAt = $clinic->subscription_ends_at;
        $daysRemaining = $endsAt && $endsAt->isFuture()
            ? now()->startOfDay()->diffInDays($endsAt->startOfDay())
            : 0;

        // Platform contact — lets the clinic reach admin to renew/upgrade.
        $phone = (string) SystemSetting::get('platform_phone', '');
        $email = (string) SystemSetting::get('platform_email', '');
        $whatsapp = preg_replace('/\D/', '', $phone);

        return response()->json([
            'data' => [
                'subscription_type'      => $clinic->subscription_type,
                'subscription_starts_at' => $clinic->subscription_starts_at?->toIso8601String(),
                'subscription_ends_at'   => $endsAt?->toIso8601String(),
                'days_remaining'         => (int) $daysRemaining,
                'is_active'              => $clinic->isSubscriptionActive(),
                'plans'                  => [
                    'basic'   => (float) SystemSetting::get('basic_subscription_price', 0),
                    'premium' => (float) SystemSetting::get('premium_subscription_price', 0),
                ],
                'contact'                => [
                    'phone'    => $phone ?: null,
                    'email'    => $email ?: null,
                    'whatsapp' => $whatsapp ?: null,
                ],
            ],
        ]);
    }
}
