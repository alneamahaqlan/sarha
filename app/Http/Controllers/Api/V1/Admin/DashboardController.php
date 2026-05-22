<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Clinic;
use App\Models\Complaint;
use App\Models\PriceQuoteRequest;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Mirrors StatsOverviewWidget + LatestBookingsWidget from Filament.
 * Same queries — just exposed over JSON for the React dashboard.
 */
class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        return response()->json([
            'data' => [
                'active_clinics'       => Clinic::where('status', 'active')->count(),
                'pending_clinics'      => Clinic::where('status', 'pending')->count(),
                'today_bookings'       => Booking::whereDate('created_at', today())->count(),
                'month_bookings'       => Booking::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)->count(),
                'active_subscriptions' => Subscription::where('status', 'active')
                    ->where('ends_at', '>=', now())->count(),
                'month_revenue'        => (float) Subscription::where('status', 'active')
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)->sum('amount'),
                'total_users'          => User::where('is_active', true)->count(),
            ],
        ]);
    }

    public function latestBookings(): JsonResponse
    {
        $bookings = Booking::query()
            ->with(['clinic:id,name', 'service:id,name'])
            ->latest()
            ->limit(10)
            ->get();

        return response()->json([
            'data' => $bookings->map(fn (Booking $b) => [
                'id'             => $b->id,
                'reference_code' => $b->reference_code,
                'clinic'         => $b->clinic ? ['id' => $b->clinic->id, 'name' => $b->clinic->name] : null,
                'customer_name'  => $b->customer_name,
                'customer_phone' => $b->customer_phone,
                'service'        => $b->service ? ['id' => $b->service->id, 'name' => $b->service->name] : null,
                'status'         => $b->status,
                'created_at'     => $b->created_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Sidebar badge counts — mirrors Filament's getNavigationBadge() on
     * ComplaintResource (new + in_review) and PriceQuoteRequestResource (new).
     */
    public function navBadges(): JsonResponse
    {
        return response()->json([
            'data' => [
                'complaints'   => Complaint::whereIn('status', ['new', 'in_review'])->count(),
                'price_quotes' => PriceQuoteRequest::where('status', 'new')->count(),
            ],
        ]);
    }

    /**
     * Bookings created per day for the last 30 days — used by the Recharts
     * line chart on the React dashboard. Not in Filament; new presentation
     * layer only, same data source (Booking).
     */
    public function bookingsTrend(): JsonResponse
    {
        $rows = Booking::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'data' => $rows->map(fn ($r) => [
                'date'  => $r->date,
                'count' => (int) $r->count,
            ]),
        ]);
    }
}
