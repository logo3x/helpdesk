<?php

namespace App\Filament\Soporte\Widgets;

use App\Filament\Concerns\HasHelpLabels;
use App\Filament\Support\SlaDefinitions;
use App\Models\SatisfactionSurvey;
use App\Models\Ticket;
use App\Services\SlaService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Calidad del servicio en los últimos 30 días: cumplimiento del SLA de
 * resolución, tiempo promedio de primera respuesta y satisfacción.
 *
 * Usa el mismo "?" de ayuda, los mismos textos (SlaDefinitions) y el
 * mismo semáforo que TicketResolutionMetricsWidget y AgentRankingWidget.
 * El scope por departamento también es el mismo: los roles que no son
 * cross-depto solo ven los tickets de su departamento.
 */
class SlaComplianceWidget extends StatsOverviewWidget
{
    use HasHelpLabels;

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Calidad del servicio (últimos 30 días)';

    protected function getStats(): array
    {
        $since = now()->subDays(30);

        $resolvedWithSla = $this->scopedTickets()
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', $since)
            ->whereNotNull('sla_config_id');

        $totalWithSla = (clone $resolvedWithSla)->count();
        $breachedCount = (clone $resolvedWithSla)->where('resolution_breached', true)->count();
        $compliance = $totalWithSla > 0
            ? (int) round((($totalWithSla - $breachedCount) / $totalWithSla) * 100)
            : null;

        $avgFirstResponse = $this->scopedTickets()
            ->whereNotNull('first_responded_at')
            ->where('first_responded_at', '>=', $since)
            ->selectRaw($this->avgMinutesExpression('created_at', 'first_responded_at').' as avg_min')
            ->value('avg_min');

        $surveys = SatisfactionSurvey::query()
            ->whereNotNull('responded_at')
            ->where('responded_at', '>=', $since)
            ->whereHas('ticket', fn (Builder $ticket) => $this->applyDepartmentScope($ticket));
        $avgRating = (clone $surveys)->avg('rating');
        $surveyCount = (clone $surveys)->count();

        return [
            Stat::make(static::helpLabel('Cumplimiento SLA', SlaDefinitions::help('sla_compliance')), $compliance === null ? '—' : $compliance.'%')
                ->description($totalWithSla > 0
                    ? "{$breachedCount} de {$totalWithSla} fuera de plazo"
                    : 'Sin tickets resueltos con SLA')
                ->descriptionIcon($compliance === null || $compliance >= SlaDefinitions::COMPLIANCE_GOOD ? 'heroicon-m-check-badge' : 'heroicon-m-exclamation-triangle')
                ->color(SlaDefinitions::complianceColor($compliance)),

            Stat::make(static::helpLabel('Primera respuesta (prom.)', SlaDefinitions::help('first_response_avg')), $avgFirstResponse !== null
                ? SlaService::formatMinutes((int) round((float) $avgFirstResponse))
                : '—')
                ->description('Tiempo calendario hasta la primera respuesta')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),

            Stat::make(static::helpLabel('Satisfacción (CSAT)', SlaDefinitions::help('csat')), $avgRating ? number_format((float) $avgRating, 1).' / 5' : '—')
                ->description($surveyCount === 1 ? '1 encuesta respondida' : "{$surveyCount} encuestas respondidas")
                ->descriptionIcon('heroicon-m-star')
                ->color($avgRating && $avgRating >= 4 ? 'success' : 'warning'),
        ];
    }

    /**
     * @return Builder<Ticket>
     */
    protected function scopedTickets(): Builder
    {
        return $this->applyDepartmentScope(Ticket::query());
    }

    /**
     * Mismo scope que TicketResolutionMetricsWidget.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    protected function applyDepartmentScope(Builder $query): Builder
    {
        $user = auth()->user();

        if ($user && ! $user->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte']) && $user->department_id) {
            $query->where('department_id', $user->department_id);
        }

        return $query;
    }

    protected function avgMinutesExpression(string $from, string $to): string
    {
        return match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => "AVG(TIMESTAMPDIFF(MINUTE, {$from}, {$to}))",
            default => "AVG((julianday({$to}) - julianday({$from})) * 24 * 60)",
        };
    }
}
