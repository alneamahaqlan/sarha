<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Clinic;
use App\Models\Complaint;
use App\Models\PriceQuoteRequest;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ClinicStatsService;
use App\Services\PlatformStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
                // Services/offers/packages sitting unbooked in customers' carts.
                'abandoned_carts_items' => \App\Models\CartItem::abandoned()->count(),
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
     * Dashboard secondary sections: subscriptions expiring within 10 days,
     * top clinics by booking count, and clinic distribution by city.
     */
    public function sections(): JsonResponse
    {
        $reminderDays = (int) (\App\Models\SystemSetting::where('key', 'subscription_reminder_days')->value('value') ?? 10);

        $expiringSoon = Clinic::query()
            ->whereNotNull('subscription_ends_at')
            ->whereBetween('subscription_ends_at', [now(), now()->addDays($reminderDays)])
            ->orderBy('subscription_ends_at')
            ->limit(10)
            ->get(['id', 'name', 'subscription_type', 'subscription_ends_at'])
            ->map(fn (Clinic $c) => [
                'id'                   => $c->id,
                'name'                 => $c->name,
                'subscription_type'    => $c->subscription_type,
                'subscription_ends_at' => $c->subscription_ends_at?->toIso8601String(),
                'days_left'            => (int) ceil(now()->diffInDays($c->subscription_ends_at, false)),
            ]);

        $topClinics = Clinic::query()
            ->withCount(['bookings' => fn ($q) => $q->where('bookings.created_at', '>=', now()->subDays(30))])
            ->orderByDesc('bookings_count')
            ->limit(5)
            ->get(['id', 'name', 'subscription_type'])
            ->map(fn (Clinic $c) => [
                'id'                => $c->id,
                'name'              => $c->name,
                'subscription_type' => $c->subscription_type,
                'bookings_count'    => (int) $c->bookings_count,
            ]);

        $byCity = \App\Models\City::query()
            ->withCount('clinics')
            ->having('clinics_count', '>', 0)
            ->orderByDesc('clinics_count')
            ->limit(10)
            ->get(['id', 'name'])
            ->map(fn ($city) => [
                'id'            => $city->id,
                'name'          => $city->name,
                'clinics_count' => (int) $city->clinics_count,
            ]);

        return response()->json([
            'data' => [
                'expiring_soon' => $expiringSoon,
                'top_clinics'   => $topClinics,
                'by_city'       => $byCity,
            ],
        ]);
    }

    /**
     * Sidebar badge counts — mirrors Filament's getNavigationBadge() on
     * ComplaintResource (new + in_review) and PriceQuoteRequestResource (new).
     * Also surfaces the two daily-workflow counters the React panel highlights:
     * clinics awaiting approval and subscriptions about to expire.
     */
    public function navBadges(): JsonResponse
    {
        // Mirror SubscriptionController's "expiring" filter exactly so the badge
        // count matches the filtered list (active + ending within reminder window).
        $reminderDays = (int) (\App\Models\SystemSetting::where('key', 'subscription_reminder_days')->value('value') ?? 10);

        return response()->json([
            'data' => [
                'complaints'             => Complaint::whereIn('status', ['new', 'in_review'])->count(),
                'price_quotes'           => PriceQuoteRequest::where('status', 'new')->count(),
                'clinics_pending'        => Clinic::where('status', 'pending')->count(),
                'subscriptions_expiring' => Subscription::where('status', 'active')
                    ->whereBetween('ends_at', [now(), now()->addDays($reminderDays)])
                    ->count(),
                // New-specialty requests pending admin review. Surfaces in the
                // sidebar so the queue is never forgotten.
                'category_requests'      => \App\Models\CategoryRequest::where('status', 'pending')->count(),
                // New canonical-service requests pending admin review (catalog).
                'catalog_requests'       => \App\Models\CatalogService::where('status', 'pending')->count(),
                // Managed-campaign requests submitted by complexes, awaiting run.
                'campaign_requests'      => \App\Models\ClinicCampaign::where('type', 'managed')
                    ->where('managed_status', 'submitted')->count(),
                // Clinic-side platform reports waiting for review.
                'clinic_reports'         => \App\Models\ClinicReport::whereIn('status', ['new', 'in_review'])->count(),
                // Customer-side platform reports waiting for review.
                'customer_reports'       => \App\Models\CustomerReport::whereIn('status', ['new', 'in_review'])->count(),
                // Complexes awaiting tracking-pixel activation approval.
                'tracking_pending'       => Clinic::where('tracking_status', 'pending')->count(),
                // Complexes awaiting cart-feature activation approval.
                'cart_pending'           => Clinic::where('cart_status', 'pending')->count(),
                // Unified Access Center badge: every registry gate in 'pending'
                // + the external request queues (category / catalog / campaign).
                'access_center'          => collect(\App\Support\ClinicGateRegistry::gates())
                        ->sum(fn ($g) => Clinic::where($g['status'], 'pending')->count())
                    + \App\Models\CategoryRequest::where('status', 'pending')->count()
                    + \App\Models\CatalogService::where('status', 'pending')->count()
                    + \App\Models\ClinicCampaign::where('type', 'managed')->where('managed_status', 'submitted')->count()
                    + \App\Models\LandingPage::where('approval_status', 'pending')->whereNotNull('owner_clinic_id')->count(),
                // Sales leads whose follow-up time has passed (still open).
                'sales_followups_overdue' => \App\Models\SalesLead::whereNotNull('next_follow_up_at')
                    ->where('next_follow_up_at', '<=', now())
                    ->whereNotIn('status', \App\Models\SalesLead::TERMINAL_STATUSES)
                    ->count(),
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

    /**
     * Per-clinic stats — the full ClinicStatsService payload (same data the
     * clinic sees in its own panel). Accepts a custom from/to range or a quick
     * period; still includes the cards/trend/kpis keys the React page reads.
     */
    public function clinicStats(Clinic $clinic, Request $request, ClinicStatsService $stats): JsonResponse
    {
        [$from, $to] = ClinicStatsService::resolveRange(
            $request->query('from'),
            $request->query('to'),
            (int) $request->query('period', 0),
        );

        // showAi=true — super-admin gets full visibility into every
        // impression source including the AI assistant attribution.
        return response()->json(['data' => $stats->compute($clinic, $from, $to, showAi: true)]);
    }

    /**
     * Platform analytics — the admin analog of the clinic "My stats" page.
     * Accepts the same custom from/to range or quick period (7/14/30/90) the
     * per-clinic stats page uses, and returns the full platform-wide payload
     * (impressions + per-source breakdown, engagement, funnel, trend,
     * distributions, best days, top clinics/services, revenue growth, and
     * period-over-period deltas) computed by PlatformStatsService.
     */
    public function analytics(Request $request, PlatformStatsService $platform): JsonResponse
    {
        [$from, $to] = ClinicStatsService::resolveRange(
            $request->query('from'),
            $request->query('to'),
            (int) $request->query('period', 0),
        );

        return response()->json(['data' => $platform->compute($from, $to)]);
    }
}
