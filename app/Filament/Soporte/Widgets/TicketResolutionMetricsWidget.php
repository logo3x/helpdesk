<?php

namespace App\Filament\Soporte\Widgets;

use App\Models\Ticket;
use App\Services\SlaService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Métricas operativas de tiempos sobre tickets resueltos en los últimos 30 días.
 *
 * Las cuatro stats responden las preguntas clave que aparecen en las
 * definiciones de SLA del proyecto (docs/pendientes.md #15-#17):
 *
 *  1. Tiempo de solución promedio  → cuánto trabajó el agente en promedio
 *     (minutos hábiles entre created_at y resolved_at, descontando paused_minutes).
 *  2. Tiempo pausado promedio      → cuánto esperan los agentes al cliente.
 *  3. Resuelto → Cerrado promedio  → cuánto tarda el cliente en confirmar
 *     la solución (tiempo calendario).
 *  4. % tickets resueltos en SLA   → porcentaje de cumplimiento del objetivo
 *     de resolution_due_at sobre el universo de tickets resueltos.
 *
 * Solo cuenta tickets `resuelto` o `cerrado` del depto del usuario (o todos
 * si es super_admin/admin/supervisor cross-depto).
 */
class TicketResolutionMetricsWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Tiempos de resolución (últimos 30 días)';

    protected function getStats(): array
    {
        $tickets = $this->ticketsQuery()->get();

        if ($tickets->isEmpty()) {
            return [
                Stat::make('Sin datos', '—')
                    ->description('No hay tickets resueltos en los últimos 30 días para el depto actual.')
                    ->color('gray'),
            ];
        }

        $sla = app(SlaService::class);

        $solutionMinutes = $tickets->map(function (Ticket $t) use ($sla) {
            $total = $sla->businessMinutesBetween($t->created_at, $t->resolved_at);

            return max(0, $total - (int) $t->paused_minutes);
        });

        $pausedMinutes = $tickets->pluck('paused_minutes')->map(fn ($m) => (int) $m);

        $resolvedToClosedHours = $tickets
            ->filter(fn (Ticket $t) => $t->closed_at !== null)
            ->map(fn (Ticket $t) => $t->resolved_at->diffInHours($t->closed_at));

        $slaCompliancePct = $this->slaCompliance($tickets);

        return [
            Stat::make('Tiempo de solución (prom.)', $this->formatMinutes((int) round($solutionMinutes->avg())))
                ->description('Trabajo efectivo del agente (sin pausa)')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color('success'),

            Stat::make('Tiempo pausado (prom.)', $this->formatMinutes((int) round($pausedMinutes->avg())))
                ->description('Espera al cliente en estado pendiente_cliente')
                ->descriptionIcon('heroicon-m-pause-circle')
                ->color($pausedMinutes->avg() > 240 ? 'warning' : 'info'),

            Stat::make('Resuelto → Cerrado (prom.)', $resolvedToClosedHours->isEmpty()
                ? '—'
                : number_format($resolvedToClosedHours->avg(), 1).' h')
                ->description('Cuánto tarda el cliente en confirmar')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('gray'),

            Stat::make('% en SLA', $slaCompliancePct === null ? '—' : $slaCompliancePct.'%')
                ->description('Resueltos antes del resolution_due_at')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color(match (true) {
                    $slaCompliancePct === null => 'gray',
                    $slaCompliancePct >= 90 => 'success',
                    $slaCompliancePct >= 75 => 'warning',
                    default => 'danger',
                }),
        ];
    }

    protected function ticketsQuery()
    {
        $user = auth()->user();
        $query = Ticket::query()
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', now()->subDays(30));

        // Scope por depto para roles que no son cross-depto.
        if ($user && ! $user->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte']) && $user->department_id) {
            $query->where('department_id', $user->department_id);
        }

        return $query;
    }

    protected function slaCompliance($tickets): ?int
    {
        $withSla = $tickets->filter(fn (Ticket $t) => $t->resolution_due_at !== null);

        if ($withSla->isEmpty()) {
            return null;
        }

        $met = $withSla->filter(fn (Ticket $t) => $t->resolved_at->lte($t->resolution_due_at))->count();

        return (int) round(($met / $withSla->count()) * 100);
    }

    protected function formatMinutes(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes.' min';
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        if ($hours < 24) {
            return $mins > 0 ? "{$hours}h {$mins}m" : "{$hours}h";
        }

        $days = intdiv($hours, 8); // días hábiles (jornada de 8h)
        $remHours = $hours % 8;

        return $remHours > 0 ? "{$days}d {$remHours}h" : "{$days}d";
    }
}
