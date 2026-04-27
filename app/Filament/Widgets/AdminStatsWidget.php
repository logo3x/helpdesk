<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Tickets\TicketResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Soporte\Resources\KbArticles\KbArticleResource;
use App\Models\Asset;
use App\Models\KbArticle;
use App\Models\SatisfactionSurvey;
use App\Models\Ticket;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminStatsWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $openTickets = Ticket::query()->open()->count();
        $totalTickets = Ticket::withTrashed()->count();
        $breached = Ticket::query()->open()->where('resolution_breached', true)->count();
        $totalUsers = User::count();
        $totalAssets = Asset::query()->active()->count();
        $kbArticles = KbArticle::where('status', 'published')->count();

        $avgRating = SatisfactionSurvey::whereNotNull('responded_at')->avg('rating');
        $csat = $avgRating ? number_format($avgRating, 1).'/5' : '—';

        // URLs base con el shape de tableFilters que Filament v5 espera.
        $ticketsBase = TicketResource::getUrl('index');
        $openStatuses = ['nuevo', 'asignado', 'en_progreso', 'pendiente_cliente', 'reabierto'];

        return [
            Stat::make('Tickets abiertos', $openTickets)
                ->description($breached > 0 ? "{$breached} con SLA vencido" : 'Sin SLA vencidos')
                ->descriptionIcon($breached > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($breached > 0 ? 'danger' : 'success')
                // Si hay vencidos, llevamos a la vista filtrada por
                // resolution_breached=true para que el admin vaya
                // directo al problema.
                ->url(
                    $breached > 0
                        ? $ticketsBase.'?'.http_build_query([
                            'tableFilters' => [
                                'status' => ['values' => $openStatuses],
                                'resolution_breached' => ['value' => '1'],
                            ],
                        ])
                        : $ticketsBase.'?'.http_build_query([
                            'tableFilters' => ['status' => ['values' => $openStatuses]],
                        ])
                ),

            Stat::make('Total tickets', $totalTickets)
                ->description('Histórico completo')
                ->descriptionIcon('heroicon-m-ticket')
                ->color('primary')
                ->url($ticketsBase),

            Stat::make('Usuarios', $totalUsers)
                ->description("{$totalAssets} activos en inventario")
                ->descriptionIcon('heroicon-m-users')
                ->color('info')
                ->url(UserResource::getUrl('index')),

            Stat::make('KB publicados', $kbArticles)
                ->description('Artículos activos')
                ->descriptionIcon('heroicon-m-book-open')
                ->color('warning')
                // Cross-panel: el admin no tiene KB Resource propio,
                // pero al ser admin puede entrar al panel /soporte sin
                // restricción.
                ->url(KbArticleResource::getUrl('index', panel: 'soporte').'?'.http_build_query([
                    'tableFilters' => ['status' => ['value' => 'published']],
                ])),

            Stat::make('Satisfacción (CSAT)', $csat)
                ->description('Promedio de encuestas')
                ->descriptionIcon('heroicon-m-star')
                ->color('success'),
        ];
    }
}
