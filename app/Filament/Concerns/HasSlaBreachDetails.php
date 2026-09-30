<?php

namespace App\Filament\Concerns;

use App\Enums\TicketPriority;
use App\Models\Department;
use App\Models\EscalationLog;
use App\Models\Ticket;
use App\Services\SlaService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Detalle de incumplimientos SLA compartido por los reportes SLA de
 * los paneles /admin y /soporte.
 *
 * - "Tickets con SLA incumplido": los tickets resueltos en el rango
 *   con `resolution_breached = true` (los mismos que cuenta el KPI),
 *   con el tiempo real consumido vs el límite. Filtrable por
 *   departamento × prioridad desde la matriz.
 * - "Últimas escalaciones": alertas 70% / 90% / incumplimiento que
 *   registra SlaService::checkBreaches(), con el ticket enriquecido.
 */
trait HasSlaBreachDetails
{
    /**
     * Filtro opcional de la tabla de incumplidos, activado al hacer clic
     * en el contador de incumplidos de una celda de la matriz.
     */
    public ?int $breachDepartmentId = null;

    public ?string $breachPriority = null;

    /**
     * Nombre de la ruta de "ver ticket" del panel donde vive la página.
     */
    abstract protected function ticketViewRouteName(): string;

    public function filterBreaches(?int $departmentId = null, ?string $priority = null): void
    {
        $this->breachDepartmentId = $departmentId;
        $this->breachPriority = TicketPriority::tryFrom((string) $priority)?->value;
    }

    public function clearBreachFilter(): void
    {
        $this->breachDepartmentId = null;
        $this->breachPriority = null;
    }

    /**
     * @return array{department: ?string, priority: ?string}|null
     */
    protected function breachFilterLabel(): ?array
    {
        if ($this->breachDepartmentId === null && $this->breachPriority === null) {
            return null;
        }

        return [
            'department' => $this->breachDepartmentId
                ? Department::find($this->breachDepartmentId)?->name
                : null,
            'priority' => TicketPriority::tryFrom((string) $this->breachPriority)?->getLabel(),
        ];
    }

    /**
     * Tickets resueltos dentro del rango cuyo SLA de resolución se
     * incumplió, con tiempos calculados en minutos hábiles.
     *
     * @return SupportCollection<int, array{ticket: Ticket, limit_minutes: int, elapsed_minutes: int, overdue_minutes: int, consumed_percent: ?float}>
     */
    protected function breachedTickets(CarbonInterface $from, CarbonInterface $to, ?int $scopeDepartmentId = null): SupportCollection
    {
        $sla = app(SlaService::class);

        $query = Ticket::query()
            ->whereNotNull('sla_config_id')
            ->whereNotNull('resolved_at')
            ->whereBetween('resolved_at', [$from, $to])
            ->where('resolution_breached', true)
            ->with([
                'department:id,name',
                'requester:id,name',
                'assignee:id,name',
                'slaConfig:id,first_response_minutes,resolution_minutes',
            ])
            ->orderByDesc('resolved_at')
            ->limit(100);

        if ($scopeDepartmentId) {
            $query->where('department_id', $scopeDepartmentId);
        }

        if ($this->breachDepartmentId) {
            $query->where('department_id', $this->breachDepartmentId);
        }

        if ($this->breachPriority) {
            $query->where('priority', $this->breachPriority);
        }

        return $query->get()->map(function (Ticket $ticket) use ($sla) {
            $limit = (int) ($ticket->slaConfig?->resolution_minutes ?? 0);
            $elapsed = max(0, $sla->businessMinutesBetween($ticket->created_at, $ticket->resolved_at) - (int) $ticket->paused_minutes);

            return [
                'ticket' => $ticket,
                'limit_minutes' => $limit,
                'elapsed_minutes' => $elapsed,
                'overdue_minutes' => max(0, $elapsed - $limit),
                'consumed_percent' => $limit > 0 ? round($elapsed / $limit * 100) : null,
            ];
        });
    }

    /**
     * Escalaciones registradas dentro del rango del reporte.
     *
     * @return Collection<int, EscalationLog>
     */
    protected function escalationsInRange(CarbonInterface $from, CarbonInterface $to, ?int $scopeDepartmentId = null): Collection
    {
        $query = EscalationLog::query()
            ->whereBetween('created_at', [$from, $to])
            ->with([
                'ticket:id,number,subject,department_id,assigned_to_id,priority,status,resolved_at',
                'ticket.department:id,name',
                'ticket.assignee:id,name',
                'notifiedUser:id,name',
            ])
            ->latest()
            ->limit(50);

        if ($scopeDepartmentId) {
            $query->whereHas('ticket', fn ($q) => $q->where('department_id', $scopeDepartmentId));
        }

        return $query->get();
    }
}
