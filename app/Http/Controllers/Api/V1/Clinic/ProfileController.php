<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clinic\UpdateProfileRequest;
use App\Http\Resources\Api\V1\ClinicResource as ClinicApiResource;
use App\Models\SubscriptionPackage;
use App\Models\SystemSetting;
use App\Services\FeatureGate;
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
     * Current subscription state, package snapshot (from FeatureGate),
     * usage meters, feature flags, and the catalog of all packages so
     * the clinic can compare tiers. Per product spec the clinic does
     * NOT upgrade themselves — they contact admin (out-of-band bank
     * transfer), and the admin assigns the package + duration. So the
     * payload exposes contact info, not a checkout flow.
     */
    public function subscription(FeatureGate $gate): JsonResponse
    {
        $clinic = auth('clinic')->user();

        // Source of truth for dates is the latest active subscription
        // row — falling back to whatever's denormalised on the clinic
        // (legacy data) so empty histories don't crash the UI.
        $current = $clinic->subscriptions()
            ->where('status', 'active')
            ->with('package:id,slug,name_ar,name_en,color_token,monthly_price')
            ->latest('starts_at')
            ->first();

        $endsAt = $current?->ends_at ?? $clinic->subscription_ends_at;
        $startsAt = $current?->starts_at ?? $clinic->subscription_starts_at;

        // Signed days — negative when expired so the UI can decide
        // between "expires in N days" vs "expired N days ago".
        $daysRemaining = $endsAt
            ? (int) round(now()->startOfDay()->diffInDays($endsAt->startOfDay(), false))
            : 0;

        $snapshot = $gate->snapshot($clinic);

        // Whole package catalog so the comparison grid can render
        // every tier (locked vs current). Ordered + active only.
        $packages = SubscriptionPackage::active()
            ->ordered()
            ->get()
            ->map(fn ($p) => [
                'id'                          => $p->id,
                'slug'                        => $p->slug,
                'name_ar'                     => $p->name_ar,
                'name_en'                     => $p->name_en,
                'tagline_ar'                  => $p->tagline_ar,
                'tagline_en'                  => $p->tagline_en,
                'color_token'                 => $p->color_token,
                'monthly_price'               => (float) $p->monthly_price,
                'services_limit'              => $p->services_limit,
                'articles_monthly_limit'      => $p->articles_monthly_limit,
                'quote_replies_monthly_limit' => $p->quote_replies_monthly_limit,
                'banner_slots'                => (int) $p->banner_slots,
                'ai_article_generation'       => (bool) $p->ai_article_generation,
                'featured_in_search'          => (bool) $p->featured_in_search,
                'ai_assistant_priority'       => (int) $p->ai_assistant_priority,
                'google_reviews_sync'         => (bool) $p->google_reviews_sync,
                'verified_badge'              => (bool) $p->verified_badge,
                'analytics_level'             => $p->analytics_level,
                'allow_offers_packages'       => (bool) $p->allow_offers_packages,
                'allow_doctors_before_after'  => (bool) $p->allow_doctors_before_after,
            ]);

        // Platform contact — admin's WhatsApp/phone/email for the
        // "contact us to upgrade" CTA.
        $phone = (string) SystemSetting::get('platform_phone', '');
        $email = (string) SystemSetting::get('platform_email', '');
        $whatsapp = preg_replace('/\D/', '', $phone);

        return response()->json([
            'data' => [
                // Legacy fields kept so any older caller still works.
                'subscription_type'      => $clinic->subscription_type,
                'subscription_starts_at' => $startsAt?->toIso8601String(),
                'subscription_ends_at'   => $endsAt?->toIso8601String(),
                'days_remaining'         => $daysRemaining,
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
                // New (Phase E) — package-driven shape.
                'current_subscription'   => $current ? [
                    'id'             => $current->id,
                    'status'         => $current->status,
                    'billing_cycle'  => $current->billing_cycle,
                    'bonus_months'   => (int) $current->bonus_months,
                    'starts_at'      => $current->starts_at?->toIso8601String(),
                    'ends_at'        => $current->ends_at?->toIso8601String(),
                    'amount'         => (float) $current->amount,
                ] : null,
                'snapshot'               => $snapshot,
                'packages'               => $packages,
            ],
        ]);
    }
}
