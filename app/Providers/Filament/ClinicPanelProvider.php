<?php

namespace App\Providers\Filament;

use App\Http\Middleware\SetLocale;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class ClinicPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('clinic')
            ->path('clinic-dashboard')
            ->login()
            ->authGuard('clinic')
            ->colors([
                'primary' => Color::hex('#0EA5E9'),
            ])
            ->brandName(fn() => __('admin.clinic_brand'))
            ->discoverResources(in: app_path('Filament/Clinic/Resources'), for: 'App\Filament\Clinic\Resources')
            ->discoverPages(in: app_path('Filament/Clinic/Pages'), for: 'App\Filament\Clinic\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Clinic/Widgets'), for: 'App\Filament\Clinic\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->navigationGroups([
                NavigationGroup::make('إدارة خدماتي')->label(fn() => __('admin.group_my_services')),
                NavigationGroup::make('الحجوزات والعملاء')->label(fn() => __('admin.group_bookings')),
                NavigationGroup::make('المحتوى والمقالات')->label(fn() => __('admin.group_articles')),
                NavigationGroup::make('الإعدادات')->label(fn() => __('admin.group_settings')),
            ])
            ->userMenuItems([
                MenuItem::make()
                    ->label(fn() => app()->getLocale() === 'ar' ? 'English' : 'العربية')
                    ->icon('heroicon-o-language')
                    ->url(fn() => route('lang.switch', app()->getLocale() === 'ar' ? 'en' : 'ar')),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetLocale::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
