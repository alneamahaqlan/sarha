<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ClinicStat;
use App\Models\GoogleReview;
use App\Models\Offer;
use App\Models\PriceQuoteRequest;
use App\Models\Service;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;

/**
 * Mirrors ClinicStatsWidget from the Filament clinic panel.
 * Same queries — scoped to auth('clinic')->id() exactly like the widget.
 */
class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $clinic = auth('clinic')->user();
        $clinicId = $clinic->id;

        $totalBookings = Booking::where('clinic_id', $clinicId)->count();
        $newBookings   = Booking::where('clinic_id', $clinicId)->where('status', 'new')->count();
        $monthBookings = Booking::where('clinic_id', $clinicId)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $totalServices = Service::where('clinic_id', $clinicId)->where('is_active', true)->count();

        // Google rating — averaged across synced reviews (null when none yet).
        $reviews = GoogleReview::where('clinic_id', $clinicId);
        $reviewsCount = (clone $reviews)->count();
        $googleRating = $reviewsCount > 0 ? round((clone $reviews)->avg('rating'), 1) : null;

        // Visibility performance — summed over the trailing 30 days from clinic_stats.
        $since = now()->subDays(30)->toDateString();
        $window = ClinicStat::where('clinic_id', $clinicId)->where('date', '>=', $since);
        $visibility = [
            'impressions' => (int) (clone $window)->sum('search_appearances'),
            'visits'      => (int) (clone $window)->sum('page_views'),
            'requests'    => (int) ((clone $window)->sum('bookings_count') + (clone $window)->sum('quote_requests_count')),
        ];

        // Latest 3 services added — mirrors the "recently added" spec section.
        $latestServices = Service::where('clinic_id', $clinicId)
            ->latest('created_at')
            ->limit(3)
            ->get(['id', 'name', 'price', 'is_active', 'created_at'])
            ->map(fn (Service $s) => [
                'id'         => $s->id,
                'name'       => $s->name,
                'price'      => (float) $s->price,
                'is_active'  => (bool) $s->is_active,
                'created_at' => $s->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'data' => [
                'total_bookings'           => $totalBookings,
                'new_bookings'             => $newBookings,
                'month_bookings'           => $monthBookings,
                'active_services'          => $totalServices,
                'subscription_type'        => $clinic->subscription_type,
                'subscription_ends_at'     => $clinic->subscription_ends_at?->toIso8601String(),
                'is_subscription_active'   => method_exists($clinic, 'isSubscriptionActive')
                    ? (bool) $clinic->isSubscriptionActive()
                    : ($clinic->subscription_ends_at && $clinic->subscription_ends_at->isFuture()),
                'google_rating'            => $googleRating,
                'google_reviews_count'     => $reviewsCount,
                'visibility'               => $visibility,
                'latest_services'          => $latestServices,
            ],
        ]);
    }

    /**
     * Sidebar badge count — mirrors Filament's
     * Clinic\PriceQuoteRequestResource::getNavigationBadge() (scoped 'new'
     * quotes for this clinic).
     */
    public function navBadges(): JsonResponse
    {
        $clinic = auth('clinic')->user();
        $clinicId = $clinic->id;

        $reminderDays = (int) (SystemSetting::get('subscription_reminder_days', 10));

        $subscriptionExpiring = (
            $clinic->subscription_ends_at
            && $clinic->subscription_ends_at->isFuture()
            && $clinic->subscription_ends_at->lte(now()->addDays($reminderDays))
        ) ? 1 : 0;

        // Active offers ending in the next 3 days — surfaces to the
        // sidebar so the admin can extend or refresh them.
        $offerExpiring = Offer::where('clinic_id', $clinicId)
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->whereBetween('ends_at', [now(), now()->addDays(3)])
            ->count();

        return response()->json([
            'data' => [
                // Broadcast requests targeting this complex's city that it hasn't replied to yet.
                'price_quotes' => PriceQuoteRequest::whereHas('cities', fn ($q) => $q->where('cities.id', $clinic->city_id))
                    ->whereDoesntHave('replies', fn ($q) => $q->where('clinic_id', $clinicId))
                    ->count(),
                'subscription_expiring' => $subscriptionExpiring,
                'offer_expiring'        => $offerExpiring,
                // Customer complaints raised against this complex that the
                // admin hasn't resolved yet. Surfaces so the complex knows
                // a customer is awaiting follow-up.
                'complaints' => \App\Models\Complaint::where('clinic_id', $clinicId)
                    ->where('source', 'customer')
                    ->whereIn('status', ['new', 'in_review'])
                    ->count(),
            ],
        ]);
    }
}
