<?php

namespace App\Filament\Widgets;

use App\Models\Ticket;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class TicketTrendChart extends ChartWidget
{
    protected ?string $heading = 'Tickets creados (últimos 30 días)';

    protected ?string $maxHeight = '250px';

    protected function getData(): array
    {
        $days = collect(range(29, 0))->map(fn ($i) => Carbon::today()->subDays($i));

        $created = [];
        $resolved = [];
        $labels = [];

        foreach ($days as $day) {
            $labels[] = $day->format('d/m');

            $created[] = Ticket::whereDate('created_at', $day)->count();
            $resolved[] = Ticket::whereDate('resolved_at', $day)->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Creados',
                    'data' => $created,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59,130,246,0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Resueltos',
                    'data' => $resolved,
                    'borderColor' => '#22c55e',
                    'backgroundColor' => 'rgba(34,197,94,0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
