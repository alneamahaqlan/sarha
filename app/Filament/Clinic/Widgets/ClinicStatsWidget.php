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
            Stat::make('طلبات الحجز الجديدة', $newBookings)
                ->description('من إجمالي ' . $totalBookings . ' طلب')
                ->descriptionIcon('heroicon-m-calendar')
                ->color($newBookings > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-bell'),

            Stat::make('حجوزات هذا الشهر', $monthBookings)
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('primary')
                ->icon('heroicon-o-calendar'),

            Stat::make('خدماتي النشطة', $totalServices)
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('success')
                ->icon('heroicon-o-sparkles'),

            Stat::make('حالة الاشتراك', $clinic?->subscription_type === 'premium' ? 'مميز ⭐' : 'أساسي')
                ->description($clinic?->subscription_ends_at ? 'ينتهي: ' . $clinic->subscription_ends_at->format('Y/m/d') : 'غير محدد')
                ->color($clinic?->isSubscriptionActive() ? 'success' : 'danger')
                ->icon('heroicon-o-credit-card'),
        ];
    }
}
