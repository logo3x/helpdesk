<?php

namespace App\Filament\Widgets;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Filament\Widgets\ChartWidget;

class TicketsByStatusChart extends ChartWidget
{
    protected ?string $heading = 'Tickets por estado';

    protected ?string $maxHeight = '250px';

    protected function getData(): array
    {
        $counts = [];
        $labels = [];
        $colors = [];

        foreach (TicketStatus::cases() as $status) {
            $count = Ticket::where('status', $status)->count();
            $counts[] = $count;
            $labels[] = $status->getLabel();
            $colors[] = match ($status) {
                TicketStatus::Nuevo => '#38bdf8',
                TicketStatus::Asignado => '#3b82f6',
                TicketStatus::EnProgreso => '#f59e0b',
                TicketStatus::PendienteCliente => '#a1a1aa',
                TicketStatus::Resuelto => '#22c55e',
                TicketStatus::Cerrado => '#71717a',
                TicketStatus::Reabierto => '#ef4444',
            };
        }

        return [
            'datasets' => [
                [
                    'data' => $counts,
                    'backgroundColor' => $colors,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
