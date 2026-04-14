<?php

namespace App\Filament\Soporte\Widgets;

use App\Models\SatisfactionSurvey;
use App\Models\Ticket;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SlaComplianceWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        // SLA compliance — % of closed/resolved tickets NOT breached (last 30 days)
        $recentClosed = Ticket::query()
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', now()->subDays(30))
            ->whereNotNull('sla_config_id');

        $totalWithSla = (clone $recentClosed)->count();
        $breachedCount = (clone $recentClosed)->where('resolution_breached', true)->count();
        $compliance = $totalWithSla > 0
            ? round((($totalWithSla - $breachedCount) / $totalWithSla) * 100, 1)
            : 100;

        // Average first response time (business minutes, last 30 days)
        $avgFirstResponse = Ticket::query()
            ->whereNotNull('first_responded_at')
            ->where('first_responded_at', '>=', now()->subDays(30))
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, first_responded_at)) as avg_min')
            ->value('avg_min');
        $avgFrDisplay = $avgFirstResponse ? round($avgFirstResponse).' min' : '—';

        // CSAT
        $avgRating = SatisfactionSurvey::whereNotNull('responded_at')
            ->where('responded_at', '>=', now()->subDays(30))
            ->avg('rating');
        $csatDisplay = $avgRating ? number_format($avgRating, 1).'/5' : '—';
        $surveyCount = SatisfactionSurvey::whereNotNull('responded_at')
            ->where('responded_at', '>=', now()->subDays(30))
            ->count();

        return [
            Stat::make('SLA Compliance (30d)', $compliance.'%')
                ->description("{$breachedCount} de {$totalWithSla} con breach")
                ->descriptionIcon($compliance >= 90 ? 'heroicon-m-check-badge' : 'heroicon-m-exclamation-triangle')
                ->color($compliance >= 90 ? 'success' : ($compliance >= 70 ? 'warning' : 'danger')),

            Stat::make('Tiempo promedio 1ra respuesta', $avgFrDisplay)
                ->description('Últimos 30 días (tiempo real)')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),

            Stat::make('CSAT (30d)', $csatDisplay)
                ->description("{$surveyCount} encuestas respondidas")
                ->descriptionIcon('heroicon-m-star')
                ->color($avgRating && $avgRating >= 4 ? 'success' : 'warning'),
        ];
    }
}
