<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Models\Booking;
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

        $offerExpiring = Service::where('clinic_id', $clinicId)
            ->whereNotNull('old_price')
            ->whereNotNull('offer_expires_at')
            ->whereBetween('offer_expires_at', [now(), now()->addDays(3)])
            ->count();

        return response()->json([
            'data' => [
                'price_quotes' => PriceQuoteRequest::where('clinic_id', $clinicId)
                    ->where('status', 'new')->count(),
                'subscription_expiring' => $subscriptionExpiring,
                'offer_expiring'        => $offerExpiring,
            ],
        ]);
    }
}
