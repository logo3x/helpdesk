<?php

namespace App\Filament\Soporte\Pages;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Department;
use App\Models\EscalationLog;
use App\Models\Ticket;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reporte de cumplimiento SLA en el panel /soporte.
 *
 * - super_admin / admin → ven todos los departamentos.
 * - supervisor_soporte  → solo su propio departamento (scope automático
 *                          en cada query).
 * - agente / técnico    → no acceden.
 *
 * Reutiliza la vista Blade `filament.pages.sla-report` que ya tiene
 * gráficos, KPI cards con delta vs periodo anterior, sección de
 * tickets en riesgo, matriz dept×prioridad y escalaciones recientes.
 * Por eso esta page necesita exponer las MISMAS variables que el
 * Admin SlaReport: window, summary, report, atRisk, priorities,
 * escalations.
 */
class SlaReport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Reporte SLA';

    protected static ?string $title = 'Reporte de cumplimiento SLA';

    protected static ?int $navigationSort = 50;

    protected string $view = 'filament.pages.sla-report';

    /**
     * Ventana de tiempo del reporte en días (binding del <select>
     * en la vista, con wire:model.live).
     */
    public string $window = '30';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte']) ?? false;
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public function getViewData(): array
    {
        $days = (int) $this->window;

        // Scope: admin/super_admin ven todos, supervisor solo su depto.
        $user = auth()->user();
        $isAdmin = $user?->hasAnyRole(['super_admin', 'admin']) ?? false;

        $departmentsQuery = Department::query()->where('is_active', true);
        if (! $isAdmin && $user?->department_id) {
            $departmentsQuery->where('id', $user->department_id);
        }
        $departments = $departmentsQuery->orderBy('name')->get();

        $priorities = TicketPriority::cases();

        return [
            'window' => $days,
            'report' => $this->buildMatrix($departments, $priorities, $days),
            'priorities' => $priorities,
            'escalations' => $this->latestEscalations($isAdmin, $user?->department_id),
            'atRisk' => $this->atRiskTickets($isAdmin, $user?->department_id),
            'summary' => $this->summary($days, $isAdmin, $user?->department_id),
        ];
    }

    /**
     * Aplica scope por departamento a una query base de tickets
     * cuando el usuario no es admin.
     */
    protected function scopeToDepartment(Builder $query, bool $isAdmin, ?int $deptId): Builder
    {
        if (! $isAdmin && $deptId) {
            $query->where('department_id', $deptId);
        }

        return $query;
    }

    /**
     * Resumen global de la ventana: resueltos, breached, compliance %.
     *
     * @return array{resolved: int, breached: int, compliance: ?float}
     */
    protected function summary(int $days, bool $isAdmin, ?int $deptId): array
    {
        $base = $this->scopeToDepartment(
            Ticket::query()
                ->whereNotNull('sla_config_id')
                ->whereNotNull('resolved_at')
                ->where('resolved_at', '>=', now()->subDays($days)),
            $isAdmin,
            $deptId,
        );

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
     * Matriz de cumplimiento departamento × prioridad. Los depts ya
     * vienen filtrados desde getViewData() según el rol.
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
     * Tickets en riesgo (no resueltos cuya fecha límite vence en 24h
     * o ya venció), respetando el scope por depto del supervisor.
     *
     * @return \Illuminate\Support\Collection<int, array{ticket: Ticket, hours_left: float, is_breached: bool}>
     */
    protected function atRiskTickets(bool $isAdmin, ?int $deptId): \Illuminate\Support\Collection
    {
        $openStatuses = [
            TicketStatus::Nuevo,
            TicketStatus::Asignado,
            TicketStatus::EnProgreso,
            TicketStatus::PendienteCliente,
            TicketStatus::Reabierto,
        ];

        $threshold = now()->addHours(24);

        $query = Ticket::query()
            ->whereNotNull('sla_config_id')
            ->whereNotNull('resolution_due_at')
            ->whereIn('status', $openStatuses)
            ->where('resolution_due_at', '<=', $threshold)
            ->with('department:id,name', 'requester:id,name', 'assignee:id,name')
            ->orderBy('resolution_due_at')
            ->limit(25);

        $tickets = $this->scopeToDepartment($query, $isAdmin, $deptId)->get();

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
     * Últimas escalaciones, filtradas al depto del supervisor si aplica.
     *
     * @return Collection<int, EscalationLog>
     */
    protected function latestEscalations(bool $isAdmin, ?int $deptId): Collection
    {
        $query = EscalationLog::with('ticket:id,number,subject,department_id', 'notifiedUser:id,name')
            ->latest()
            ->limit(20);

        if (! $isAdmin && $deptId) {
            $query->whereHas('ticket', fn ($q) => $q->where('department_id', $deptId));
        }

        return $query->get();
    }
}
