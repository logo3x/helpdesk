<?php

namespace App\Providers\Filament;

use App\Filament\Soporte\Widgets\TicketStatsWidget;
use Awcodes\QuickCreate\QuickCreatePlugin;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use CharrafiMed\GlobalSearchModal\GlobalSearchModalPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class SoportePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('soporte')
            ->path('soporte')
            ->login()
            ->profile()
            ->brandName('Confipetrol Soporte')
            ->brandLogo(asset('images/logo-confipetrol-dark.png'))
            ->brandLogoHeight('2.5rem')
            ->darkModeBrandLogo(asset('images/logo-confipetrol.png'))
            ->colors([
                'primary' => Color::Sky,
            ])
            ->discoverResources(in: app_path('Filament/Soporte/Resources'), for: 'App\Filament\Soporte\Resources')
            ->discoverPages(in: app_path('Filament/Soporte/Pages'), for: 'App\Filament\Soporte\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Soporte/Widgets'), for: 'App\Filament\Soporte\Widgets')
            ->widgets([
                TicketStatsWidget::class,
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
                QuickCreatePlugin::make(),
                GlobalSearchModalPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
