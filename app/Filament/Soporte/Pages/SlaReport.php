<?php

namespace App\Filament\Soporte\Pages;

use App\Enums\TicketPriority;
use App\Models\Department;
use App\Models\EscalationLog;
use App\Models\Ticket;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Reporte de cumplimiento SLA en el panel /soporte.
 *
 * - super_admin / admin → ven todos los departamentos.
 * - supervisor_soporte  → solo su propio departamento (scope) +
 *                          escalaciones de tickets de su depto.
 * - agente / técnico    → no acceden.
 *
 * Reutiliza la misma vista Blade que el reporte del panel /admin.
 */
class SlaReport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Reporte SLA';

    protected static ?string $title = 'Reporte de cumplimiento SLA';

    protected static ?int $navigationSort = 50;

    protected string $view = 'filament.pages.sla-report';

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
        $user = auth()->user();
        $isAdmin = $user?->hasAnyRole(['super_admin', 'admin']) ?? false;

        // Scope: admin ve todos los deptos, supervisor solo el suyo.
        $departmentsQuery = Department::query()->where('is_active', true);
        if (! $isAdmin && $user?->department_id) {
            $departmentsQuery->where('id', $user->department_id);
        }
        $departments = $departmentsQuery->orderBy('name')->get();

        $priorities = TicketPriority::cases();

        $report = [];

        foreach ($departments as $dept) {
            $row = ['department' => $dept->name, 'priorities' => []];

            foreach ($priorities as $priority) {
                $query = Ticket::query()
                    ->where('department_id', $dept->id)
                    ->where('priority', $priority)
                    ->whereNotNull('sla_config_id')
                    ->whereNotNull('resolved_at')
                    ->where('resolved_at', '>=', now()->subDays(30));

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

        // Escalaciones recientes: si supervisor, solo de su depto.
        $escalationsQuery = EscalationLog::with('ticket:id,number,subject,department_id', 'notifiedUser:id,name')
            ->latest()
            ->limit(20);

        if (! $isAdmin && $user?->department_id) {
            $escalationsQuery->whereHas('ticket', fn ($q) => $q->where('department_id', $user->department_id));
        }

        return [
            'report' => $report,
            'priorities' => $priorities,
            'escalations' => $escalationsQuery->get(),
        ];
    }
}
