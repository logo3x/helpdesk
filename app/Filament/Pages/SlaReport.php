<?php

namespace App\Filament\Pages;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Department;
use App\Models\EscalationLog;
use App\Models\Ticket;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reporte de cumplimiento SLA.
 *
 * Vista del cumplimiento de SLA por departamento y prioridad,
 * complementada con dos secciones operativas:
 *  - "Tickets en riesgo": no resueltos cuya fecha límite de resolución
 *    está vencida o vence en las próximas 24h (mira hacia adelante).
 *  - "Últimas escalaciones": registro histórico de SLA quebrados o
 *    avisados (mira hacia atrás).
 *
 * Soporta una ventana de tiempo configurable (7/30/90/365 días) para
 * que el reporte sea útil tanto en revisión semanal como en auditoría
 * trimestral.
 */
class SlaReport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Reporte SLA';

    protected static ?string $title = 'Reporte de cumplimiento SLA';

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 50;

    protected string $view = 'filament.pages.sla-report';

    /**
     * Ventana de tiempo del reporte en días. Bindeada a un <select> en
     * la vista con wire:model.live para que el reporte se recalcule
     * sin recargar la página.
     */
    public string $window = '30';

    public function getViewData(): array
    {
        $days = (int) $this->window;
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $priorities = TicketPriority::cases();

        return [
            'window' => $days,
            'report' => $this->buildMatrix($departments, $priorities, $days),
            'priorities' => $priorities,
            'escalations' => $this->latestEscalations(),
            'atRisk' => $this->atRiskTickets(),
            'summary' => $this->summary($days),
        ];
    }

    /**
     * Resumen global de la ventana: total resueltos, breached, compliance %.
     *
     * @return array{resolved: int, breached: int, compliance: ?float}
     */
    protected function summary(int $days): array
    {
        $base = Ticket::query()
            ->whereNotNull('sla_config_id')
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', now()->subDays($days));

        $resolved = (clone $base)->count();
        $breached = (clone $base)->where('resolution_breached', true)->count();
        $compliance = $resolved > 0 ? round((($resolved - $breached) / $resolved) * 100, 1) : null;

        return [
            'resolved' => $resolved,
            'breached' => $breached,
            'compliance' => $compliance,
        ];
    }

    /**
     * Matriz de cumplimiento departamento × prioridad para la ventana
     * indicada.
     *
     * @param  Collection<int, Department>  $departments
     * @param  array<int, TicketPriority>  $priorities
     * @return array<int, array{department: string, priorities: array<int, array{label: string, total: int, breached: int, compliance: ?float}>}>
     */
    protected function buildMatrix(Collection $departments, array $priorities, int $days): array
    {
        $report = [];

        foreach ($departments as $dept) {
            $row = ['department' => $dept->name, 'priorities' => []];

            foreach ($priorities as $priority) {
                $query = Ticket::query()
                    ->where('department_id', $dept->id)
                    ->where('priority', $priority)
                    ->whereNotNull('sla_config_id')
                    ->whereNotNull('resolved_at')
                    ->where('resolved_at', '>=', now()->subDays($days));

                $total = (clone $query)->count();
                $breached = (clone $query)->where('resolution_breached', true)->count();
                $compliance = $total > 0 ? round((($total - $breached) / $total) * 100, 1) : null;

                $row['priorities'][] = [
                    'label' => $priority->getLabel(),
                    'total' => $total,
                    'breached' => $breached,
                    'compliance' => $compliance,
                ];
            }

            $report[] = $row;
        }

        return $report;
    }

    /**
     * Tickets NO resueltos cuya fecha límite ya pasó o vence en las
     * próximas 24h. Ordenados por urgencia (los ya vencidos primero).
     *
     * @return \Illuminate\Support\Collection<int, array{ticket: Ticket, hours_left: float, is_breached: bool}>
     */
    protected function atRiskTickets(): \Illuminate\Support\Collection
    {
        $openStatuses = [
            TicketStatus::Nuevo,
            TicketStatus::Asignado,
            TicketStatus::EnProgreso,
            TicketStatus::PendienteCliente,
            TicketStatus::Reabierto,
        ];

        $threshold = now()->addHours(24);

        $tickets = Ticket::query()
            ->whereNotNull('sla_config_id')
            ->whereNotNull('resolution_due_at')
            ->whereIn('status', $openStatuses)
            ->where('resolution_due_at', '<=', $threshold)
            ->with('department:id,name', 'requester:id,name', 'assignee:id,name')
            ->orderBy('resolution_due_at')
            ->limit(25)
            ->get();

        return $tickets->map(function (Ticket $t) {
            $diff = now()->diffInMinutes($t->resolution_due_at, false);

            return [
                'ticket' => $t,
                'hours_left' => round($diff / 60, 1),
                'is_breached' => $diff < 0,
            ];
        });
    }

    /**
     * @return Collection<int, EscalationLog>
     */
    protected function latestEscalations(): Collection
    {
        return EscalationLog::with('ticket:id,number,subject', 'notifiedUser:id,name')
            ->latest()
            ->limit(20)
            ->get();
    }
}
