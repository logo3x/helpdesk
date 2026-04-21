<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\AdminStatsWidget;
use App\Filament\Widgets\TicketsByStatusChart;
use App\Filament\Widgets\TicketTrendChart;
use Awcodes\QuickCreate\QuickCreatePlugin;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use CharrafiMed\GlobalSearchModal\GlobalSearchModalPlugin;
use Filament\Facades\Filament;
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
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile()
            ->brandName('Confipetrol Helpdesk')
            ->brandLogo(asset('images/logo-confipetrol-dark.png'))
            ->brandLogoHeight('2.5rem')
            ->darkModeBrandLogo(asset('images/logo-confipetrol.png'))
            ->colors([
                'primary' => Color::Amber,
            ])
            // Solo un grupo de navegación (Configuración) colapsado por
            // default. El resto de items van al nivel raíz, como Escritorio.
            ->navigationGroups([
                NavigationGroup::make('Configuración')->collapsed(),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AdminStatsWidget::class,
                TicketsByStatusChart::class,
                TicketTrendChart::class,
                AccountWidget::class,
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
                FilamentSpatieLaravelBackupPlugin::make()
                    ->navigationGroup('Configuración')
                    ->navigationLabel('Respaldos')
                    ->navigationSort(100),
            ])
            ->userMenuItems([
                // Sustituye el label por defecto "Perfil" por el nombre
                // del usuario. Sigue llevando a la página de edición de perfil.
                'profile' => MenuItem::make()
                    ->label(fn () => auth()->user()?->name ?? 'Perfil')
                    ->icon('heroicon-o-user-circle')
                    ->url(fn () => Filament::getProfileUrl()),
                'role' => MenuItem::make()
                    ->label(fn () => auth()->user()?->roleLabel() ?? 'Sin rol')
                    ->icon('heroicon-o-identification')
                    ->url('#')
                    ->sort(1),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
