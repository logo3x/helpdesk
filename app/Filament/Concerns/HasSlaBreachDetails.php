<?php

namespace App\Filament\Concerns;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Filament\Resources\SlaConfigs\SlaConfigResource;
use App\Models\Department;
use App\Models\EscalationLog;
use App\Models\SlaConfig;
use App\Models\Ticket;
use App\Services\SlaService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Detalle de incumplimientos SLA compartido por los reportes SLA de
 * los paneles /admin y /soporte.
 *
 * - "Tickets con SLA incumplido": los tickets resueltos en el rango
 *   con `resolution_breached = true` (los mismos que cuenta el KPI),
 *   con el tiempo real consumido vs el límite.
 * - "Últimas escalaciones": alertas 70% / 90% / incumplimiento que
 *   registra SlaService::checkBreaches(), con el ticket enriquecido.
 * - Ordenamiento por columna y filtros para las tres tablas del
 *   reporte (en riesgo, incumplidos, escalaciones). Se aplican del
 *   lado del servidor, así que el PDF exporta lo que se ve en pantalla.
 */
trait HasSlaBreachDetails
{
    /**
     * Filtros activos por tabla. Los valores vacíos no filtran.
     *
     * @var array<string, array<string, string>>
     */
    public array $tableFilters = [
        'risk' => ['search' => '', 'department' => '', 'priority' => '', 'agent' => ''],
        'breach' => ['search' => '', 'department' => '', 'priority' => '', 'agent' => ''],
        'escalations' => ['search' => '', 'type' => '', 'metric' => '', 'department' => '', 'agent' => '', 'status' => ''],
    ];

    /**
     * Columna y dirección de orden por tabla. Columna vacía = orden por
     * defecto de la consulta.
     *
     * @var array<string, array{column: string, direction: string}>
     */
    public array $tableSorts = [
        'risk' => ['column' => '', 'direction' => 'asc'],
        'breach' => ['column' => '', 'direction' => 'asc'],
        'escalations' => ['column' => '', 'direction' => 'asc'],
    ];

    /**
     * Nombre de la ruta de "ver ticket" del panel donde vive la página.
     */
    abstract protected function ticketViewRouteName(): string;

    /**
     * Ordena una tabla por la columna indicada. Un segundo clic sobre la
     * misma columna invierte la dirección.
     */
    public function sortTable(string $table, string $column): void
    {
        if (! array_key_exists($table, $this->tableSorts)) {
            return;
        }

        $current = $this->tableSorts[$table];

        $this->tableSorts[$table] = [
            'column' => $column,
            'direction' => $current['column'] === $column && $current['direction'] === 'asc' ? 'desc' : 'asc',
        ];
    }

    /**
     * Limpia filtros y orden de una tabla.
     */
    public function resetTableControls(string $table): void
    {
        if (! array_key_exists($table, $this->tableFilters)) {
            return;
        }

        $this->tableFilters[$table] = array_map(fn () => '', $this->tableFilters[$table]);
        $this->tableSorts[$table] = ['column' => '', 'direction' => 'asc'];
    }

    /**
     * Atajo desde la matriz: filtra la tabla de incumplidos por
     * departamento × prioridad.
     */
    public function filterBreaches(?int $departmentId = null, ?string $priority = null): void
    {
        $this->tableFilters['breach']['department'] = $departmentId ? (string) $departmentId : '';
        $this->tableFilters['breach']['priority'] = TicketPriority::tryFrom((string) $priority)?->value ?? '';
    }

    public function clearBreachFilter(): void
    {
        $this->resetTableControls('breach');
    }

    /**
     * Tickets resueltos dentro del rango cuyo SLA de resolución se
     * incumplió, con tiempos calculados en minutos hábiles.
     *
     * El departamento y la prioridad se filtran en SQL para que el tope
     * de 100 filas no esconda tickets al filtrar.
     *
     * @return Collection<int, array{ticket: Ticket, limit_minutes: int, elapsed_minutes: int, overdue_minutes: int, consumed_percent: ?float}>
     */
    protected function breachedTickets(CarbonInterface $from, CarbonInterface $to, ?int $scopeDepartmentId = null): Collection
    {
        $sla = app(SlaService::class);
        $filters = $this->tableFilters['breach'];

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

        if ($filters['department'] !== '') {
            $query->where('department_id', (int) $filters['department']);
        }

        if (TicketPriority::tryFrom($filters['priority'])) {
            $query->where('priority', $filters['priority']);
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
                'ticket:id,number,subject,department_id,assigned_to_id,requester_id,priority,status,resolved_at',
                'ticket.department:id,name',
                'ticket.assignee:id,name',
                'ticket.requester:id,name',
                'notifiedUser:id,name',
            ])
            ->latest()
            ->limit(50);

        if ($scopeDepartmentId) {
            $query->whereHas('ticket', fn ($q) => $q->where('department_id', $scopeDepartmentId));
        }

        return $query->get()->toBase();
    }

    /**
     * Aplica filtros (excepto departamento/prioridad, ya filtrados en
     * SQL) y orden a la tabla de incumplidos.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    protected function controlBreachTable(Collection $rows): Collection
    {
        return $this->applyTableControls('breach', $rows, [
            'ticket' => fn (array $r) => $r['ticket']->number,
            'department' => fn (array $r) => $r['ticket']->department?->name,
            'priority' => fn (array $r) => $this->priorityRank($r['ticket']->priority),
            'agent' => fn (array $r) => $r['ticket']->assignee?->name,
            'requester' => fn (array $r) => $r['ticket']->requester?->name,
            'limit' => fn (array $r) => $r['limit_minutes'],
            'elapsed' => fn (array $r) => $r['elapsed_minutes'],
            'overdue' => fn (array $r) => $r['overdue_minutes'],
            'resolved' => fn (array $r) => $r['ticket']->resolved_at?->getTimestamp(),
        ], [
            'search' => fn (array $r, string $q) => $this->ticketMatches($r['ticket'], $q),
            'agent' => fn (array $r, string $v) => $this->agentMatches($r['ticket'], $v),
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    protected function controlRiskTable(Collection $rows): Collection
    {
        return $this->applyTableControls('risk', $rows, [
            'ticket' => fn (array $r) => $r['ticket']->number,
            'department' => fn (array $r) => $r['ticket']->department?->name,
            'agent' => fn (array $r) => $r['ticket']->assignee?->name,
            'priority' => fn (array $r) => $this->priorityRank($r['ticket']->priority),
            'due' => fn (array $r) => $r['hours_left'],
        ], [
            'search' => fn (array $r, string $q) => $this->ticketMatches($r['ticket'], $q),
            'department' => fn (array $r, string $v) => (string) $r['ticket']->department_id === $v,
            'priority' => fn (array $r, string $v) => $r['ticket']->priority?->value === $v,
            'agent' => fn (array $r, string $v) => $this->agentMatches($r['ticket'], $v),
        ]);
    }

    /**
     * @param  Collection<int, EscalationLog>  $rows
     * @return Collection<int, EscalationLog>
     */
    protected function controlEscalationTable(Collection $rows): Collection
    {
        return $this->applyTableControls('escalations', $rows, [
            'ticket' => fn (EscalationLog $e) => $e->ticket?->number,
            'type' => fn (EscalationLog $e) => $e->isBreach() ? 100 : (int) preg_replace('/\D/', '', (string) $e->type),
            'department' => fn (EscalationLog $e) => $e->ticket?->department?->name,
            'agent' => fn (EscalationLog $e) => $e->ticket?->assignee?->name,
            'status' => fn (EscalationLog $e) => $e->ticket?->status?->getLabel(),
            'consumed' => fn (EscalationLog $e) => $e->consumedPercent(),
            'date' => fn (EscalationLog $e) => $e->created_at?->getTimestamp(),
        ], [
            'search' => fn (EscalationLog $e, string $q) => $e->ticket !== null && $this->ticketMatches($e->ticket, $q),
            'type' => fn (EscalationLog $e, string $v) => match ($v) {
                'breach' => $e->isBreach(),
                default => str_starts_with((string) $e->type, $v.'_'),
            },
            'metric' => fn (EscalationLog $e, string $v) => str_contains((string) $e->type, $v),
            'department' => fn (EscalationLog $e, string $v) => (string) $e->ticket?->department_id === $v,
            'agent' => fn (EscalationLog $e, string $v) => $e->ticket !== null && $this->agentMatches($e->ticket, $v),
            'status' => fn (EscalationLog $e, string $v) => $e->ticket?->status?->value === $v,
        ]);
    }

    /**
     * Opciones de los selectores de filtros, construidas con los datos
     * sin filtrar para que no desaparezcan al elegir una.
     *
     * @param  iterable<Department>  $departments
     * @param  Collection<int, array<string, mixed>>  $atRisk
     * @param  Collection<int, array<string, mixed>>  $breached
     * @param  Collection<int, EscalationLog>  $escalations
     * @return array{departments: array<int, string>, priorities: array<string, string>, statuses: array<string, string>, agents: array<string, string>}
     */
    protected function tableFilterOptions(iterable $departments, Collection $atRisk, Collection $breached, Collection $escalations): array
    {
        $agents = collect()
            ->merge($atRisk->map(fn (array $r) => $r['ticket']->assignee))
            ->merge($breached->map(fn (array $r) => $r['ticket']->assignee))
            ->merge($escalations->map(fn (EscalationLog $e) => $e->ticket?->assignee))
            ->filter()
            ->mapWithKeys(fn ($user) => [(string) $user->id => $user->name])
            ->sort()
            ->all();

        return [
            'departments' => collect($departments)->mapWithKeys(fn ($d) => [$d->id => $d->name])->all(),
            'priorities' => collect(TicketPriority::cases())->mapWithKeys(fn ($p) => [$p->value => $p->getLabel()])->all(),
            'statuses' => collect(TicketStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->getLabel()])->all(),
            'agents' => ['none' => '— Sin asignar —'] + $agents,
        ];
    }

    /**
     * @template TRow
     *
     * @param  Collection<int, TRow>  $rows
     * @param  array<string, callable(TRow): mixed>  $sortKeys
     * @param  array<string, callable(TRow, string): bool>  $filters
     * @return Collection<int, TRow>
     */
    protected function applyTableControls(string $table, Collection $rows, array $sortKeys, array $filters): Collection
    {
        foreach ($this->tableFilters[$table] ?? [] as $name => $value) {
            $value = trim((string) $value);

            if ($value === '' || ! isset($filters[$name])) {
                continue;
            }

            $rows = $rows->filter(fn ($row) => $filters[$name]($row, $value));
        }

        $sort = $this->tableSorts[$table] ?? null;

        if ($sort && isset($sortKeys[$sort['column']])) {
            $rows = $rows->sortBy(
                $sortKeys[$sort['column']],
                SORT_NATURAL | SORT_FLAG_CASE,
                $sort['direction'] === 'desc',
            );
        }

        return $rows->values();
    }

    /**
     * Políticas SLA vigentes por departamento × prioridad, con enlace a
     * editarlas (o a crearlas si faltan) para quien tenga acceso.
     *
     * @param  iterable<Department>  $departments
     * @return array{canEdit: bool, indexUrl: ?string, rows: array<int, array{department: string, cells: array<int, array{priority: TicketPriority, config: ?SlaConfig, url: ?string}>}>}
     */
    protected function slaPolicyMatrix(iterable $departments): array
    {
        $departments = collect($departments);
        $canEdit = SlaConfigResource::canAccess();

        $configs = SlaConfig::query()
            ->whereIn('department_id', $departments->pluck('id'))
            ->get()
            ->keyBy(fn (SlaConfig $c) => $c->department_id.'|'.$c->priority->value);

        $rows = $departments->map(fn ($department) => [
            'department' => $department->name,
            'cells' => collect(TicketPriority::cases())->map(function (TicketPriority $priority) use ($department, $configs, $canEdit) {
                $config = $configs->get($department->id.'|'.$priority->value);

                $url = null;
                if ($canEdit) {
                    $url = $config
                        ? SlaConfigResource::getUrl('edit', ['record' => $config], panel: 'admin')
                        : SlaConfigResource::getUrl('create', ['department_id' => $department->id, 'priority' => $priority->value], panel: 'admin');
                }

                return ['priority' => $priority, 'config' => $config, 'url' => $url];
            })->all(),
        ])->values()->all();

        return [
            'canEdit' => $canEdit,
            'indexUrl' => $canEdit ? SlaConfigResource::getUrl('index', panel: 'admin') : null,
            'rows' => $rows,
        ];
    }

    protected function ticketMatches(Ticket $ticket, string $search): bool
    {
        $haystack = implode(' ', [
            $ticket->number,
            $ticket->subject,
            $ticket->requester?->name,
            $ticket->assignee?->name,
        ]);

        return Str::contains($haystack, $search, ignoreCase: true);
    }

    protected function agentMatches(Ticket $ticket, string $agent): bool
    {
        return $agent === 'none'
            ? $ticket->assigned_to_id === null
            : (string) $ticket->assigned_to_id === $agent;
    }

    protected function priorityRank(?TicketPriority $priority): int
    {
        return $priority ? (int) array_search($priority, TicketPriority::cases(), true) : -1;
    }
}
