<?php

namespace App\Filament\Clinic\Widgets;

use App\Models\Booking;
use App\Models\Service;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ClinicStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $clinicId = auth('clinic')->id();

        $totalBookings = Booking::where('clinic_id', $clinicId)->count();
        $newBookings = Booking::where('clinic_id', $clinicId)->where('status', 'new')->count();
        $monthBookings = Booking::where('clinic_id', $clinicId)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $totalServices = Service::where('clinic_id', $clinicId)->where('is_active', true)->count();
        $clinic = auth('clinic')->user();

        return [
            Stat::make(__('admin.widgets.new_bookings'), $newBookings)
                ->description(__('admin.widgets.of_total_bookings', ['count' => $totalBookings]))
                ->descriptionIcon('heroicon-m-calendar')
                ->color($newBookings > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-bell'),

            Stat::make(__('admin.widgets.monthly_bookings'), $monthBookings)
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('primary')
                ->icon('heroicon-o-calendar'),

            Stat::make(__('admin.widgets.my_active_services'), $totalServices)
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('success')
                ->icon('heroicon-o-sparkles'),

            Stat::make(
                __('admin.widgets.subscription_status'),
                $clinic?->subscription_type === 'premium'
                    ? __('admin.plan.premium_with_star')
                    : __('admin.plan.basic')
            )
                ->description(
                    $clinic?->subscription_ends_at
                        ? __('admin.widgets.subscription_ends_label', ['date' => $clinic->subscription_ends_at->format('Y/m/d')])
                        : __('admin.widgets.subscription_undefined')
                )
                ->color($clinic?->isSubscriptionActive() ? 'success' : 'danger')
                ->icon('heroicon-o-credit-card'),
        ];
    }
}
