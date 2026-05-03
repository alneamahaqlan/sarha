<?php

namespace App\Filament\Widgets;

use App\Models\Clinic;
use App\Models\Booking;
use App\Models\Subscription;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $activeClinics = Clinic::where('status', 'active')->count();
        $pendingClinics = Clinic::where('status', 'pending')->count();
        $todayBookings = Booking::whereDate('created_at', today())->count();
        $monthBookings = Booking::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)->count();
        $activeSubscriptions = Subscription::where('status', 'active')
            ->where('ends_at', '>=', now())->count();
        $monthRevenue = Subscription::where('status', 'active')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');
        $totalUsers = User::where('is_active', true)->count();

        return [
            Stat::make('العيادات النشطة', $activeClinics)
                ->description($pendingClinics . ' عيادة قيد المراجعة')
                ->descriptionIcon('heroicon-m-clock')
                ->color('success')
                ->icon('heroicon-o-building-office-2'),

            Stat::make('طلبات الحجز اليوم', $todayBookings)
                ->description($monthBookings . ' طلب هذا الشهر')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('primary')
                ->icon('heroicon-o-calendar'),

            Stat::make('الاشتراكات النشطة', $activeSubscriptions)
                ->description(number_format($monthRevenue) . ' ريال إيرادات الشهر')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning')
                ->icon('heroicon-o-credit-card'),

            Stat::make('العملاء المسجلون', $totalUsers)
                ->descriptionIcon('heroicon-m-users')
                ->color('info')
                ->icon('heroicon-o-users'),
        ];
    }
}
