<?php

namespace App\Filament\Soporte\Widgets;

use App\Enums\MaintenanceStatus;
use App\Models\ScheduledMaintenance;
use Filament\Widgets\Widget;

/**
 * KPIs compactos del módulo Mantenimientos Programados.
 *
 * Renderizado con blade custom (no StatsOverview de Filament) para
 * tener control total del alto y del padding. Se muestran los 6
 * indicadores en una sola fila de tarjetas pequeñas.
 */
class MaintenancesKpiWidget extends Widget
{
    protected string $view = 'filament.soporte.widgets.maintenances-kpi';

    protected int|string|array $columnSpan = 'full';

    /**
     * Auto-refresh cada 10s. Sirve para que al borrar/crear un mtto
     * los KPIs de arriba se actualicen sin recargar la página.
     */
    protected ?string $pollingInterval = '10s';

    /**
     * @return array<string, array<int, array{label: string, value: int, hint: string, color: string, icon: string}>>
     */
    protected function getViewData(): array
    {
        return [
            'kpis' => $this->buildKpis(),
        ];
    }

    /**
     * Public helper para poder llamar desde el blade directamente si la
     * ruta getViewData no se ejecuta al re-render de Livewire.
     *
     * @return array<int, array{label: string, value: int, hint: string, color: string, icon: string}>
     */
    public function getKpis(): array
    {
        return $this->buildKpis();
    }

    /**
     * @return array<int, array{label: string, value: int, hint: string, color: string, icon: string}>
     */
    protected function buildKpis(): array
    {
        $user = auth()->user();
        $isAdmin = $user?->hasAnyRole(['super_admin', 'admin']) ?? false;
        $isSupervisor = $user?->hasRole('supervisor_soporte') ?? false;
        $isAgent = $user?->hasAnyRole(['agente_soporte', 'tecnico_campo']) ?? false;

        $query = ScheduledMaintenance::query();

        if ($isAgent) {
            $query->where('agent_id', $user->id);
        } elseif ($isSupervisor) {
            $query->where(function ($q) use ($user) {
                $q->where('agent_id', $user->id)
                    ->orWhere('created_by_id', $user->id);
                if ($user->department_id) {
                    $q->orWhereHas('asset', fn ($aq) => $aq->where('department_id', $user->department_id));
                }
            });
        } elseif (! $isAdmin) {
            return [];
        }

        $total = (clone $query)->count();
        $pendientes = (clone $query)->where('status', MaintenanceStatus::Pendiente->value)->count();
        $vencidos = (clone $query)
            ->where('status', MaintenanceStatus::Pendiente->value)
            ->where('scheduled_at', '<', now()->startOfDay())
            ->count();
        $proximos30 = (clone $query)
            ->where('status', MaintenanceStatus::Pendiente->value)
            ->whereBetween('scheduled_at', [now()->startOfDay(), now()->addDays(30)])
            ->count();
        $cumplidosMes = (clone $query)
            ->where('status', MaintenanceStatus::Cumplido->value)
            ->whereBetween('completed_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
        $noCumplidosMes = (clone $query)
            ->where('status', MaintenanceStatus::NoCumplido->value)
            ->whereBetween('completed_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        return [
            ['label' => 'Programados', 'value' => $total, 'hint' => 'Total', 'color' => 'info', 'icon' => 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2'],
            ['label' => 'Pendientes', 'value' => $pendientes, 'hint' => 'Sin ejecutar', 'color' => $pendientes > 0 ? 'warning' : 'gray', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z'],
            ['label' => 'Vencidos', 'value' => $vencidos, 'hint' => 'Fecha pasada', 'color' => $vencidos > 0 ? 'danger' : 'success', 'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'],
            ['label' => 'Próx. 30 días', 'value' => $proximos30, 'hint' => 'En ventana', 'color' => 'info', 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2z'],
            ['label' => 'Cumplidos', 'value' => $cumplidosMes, 'hint' => 'Este mes', 'color' => 'success', 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0 1 12 2.944a11.955 11.955 0 0 1-8.618 3.04A12.02 12.02 0 0 0 3 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
            ['label' => 'No cumplidos', 'value' => $noCumplidosMes, 'hint' => 'Este mes', 'color' => $noCumplidosMes > 0 ? 'danger' : 'gray', 'icon' => 'M6 18L18 6M6 6l12 12'],
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole([
            'super_admin', 'admin', 'supervisor_soporte', 'agente_soporte', 'tecnico_campo',
        ]) ?? false;
    }
}
