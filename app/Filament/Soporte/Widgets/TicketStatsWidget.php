<?php

namespace App\Filament\Soporte\Widgets;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TicketStatsWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $open = Ticket::query()->open()->count();
        $newOrReopened = Ticket::query()
            ->whereIn('status', [TicketStatus::Nuevo, TicketStatus::Reabierto])
            ->count();
        $critical = Ticket::query()
            ->open()
            ->whereIn('priority', [TicketPriority::Alta, TicketPriority::Critica])
            ->count();
        $assignedToMe = Ticket::query()
            ->open()
            ->where('assigned_to_id', auth()->id())
            ->count();

        return [
            Stat::make('Tickets abiertos', $open)
                ->description('Todos los estados activos')
                ->descriptionIcon('heroicon-m-inbox')
                ->color('primary'),

            Stat::make('Sin asignar / reabiertos', $newOrReopened)
                ->description('Requieren triage')
                ->descriptionIcon('heroicon-m-bell-alert')
                ->color($newOrReopened > 0 ? 'warning' : 'gray'),

            Stat::make('Prioridad alta/crítica', $critical)
                ->description('Abiertos con SLA crítico')
                ->descriptionIcon('heroicon-m-fire')
                ->color($critical > 0 ? 'danger' : 'success'),

            Stat::make('Asignados a mí', $assignedToMe)
                ->description('Abiertos en mi cola')
                ->descriptionIcon('heroicon-m-user')
                ->color('info'),
        ];
    }
}
