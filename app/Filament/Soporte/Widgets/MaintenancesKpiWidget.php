<?php

namespace App\Filament\Soporte\Widgets;

use App\Enums\MaintenanceStatus;
use App\Models\ScheduledMaintenance;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * KPIs del módulo Mantenimientos Programados — se muestra encima de
 * la tabla /soporte/scheduled-maintenances y /admin/scheduled-
 * maintenances.
 *
 * Métricas:
 *   - Total programados: todos los mtto activos (no soft-deleted)
 *     con scope por rol.
 *   - Pendientes: status pendiente en cualquier fecha.
 *   - Vencidos: pendientes con fecha ya pasada.
 *   - Próximos 30 días: pendientes que vencen en la próxima ventana.
 *   - Cumplidos este mes: cerraron en el mes actual.
 *   - No cumplidos este mes: no_cumplido en el mes actual.
 */
class MaintenancesKpiWidget extends StatsOverviewWidget
{
    /** Widget compacto — 3 columnas para que se vean varios KPIs. */
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $user = auth()->user();
        $isAdmin = $user?->hasAnyRole(['super_admin', 'admin']) ?? false;
        $isSupervisor = $user?->hasRole('supervisor_soporte') ?? false;
        $isAgent = $user?->hasAnyRole(['agente_soporte', 'tecnico_campo']) ?? false;

        $query = ScheduledMaintenance::query();

        // Scope por rol — mismo que en el resource.
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
            // Rol no autorizado, no muestra nada.
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
            Stat::make('Programados', $total)
                ->description('Total activos en el sistema')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('info'),

            Stat::make('Pendientes', $pendientes)
                ->description('Aún no ejecutados')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendientes > 0 ? 'warning' : 'gray'),

            Stat::make('Vencidos', $vencidos)
                ->description('Pendientes con fecha pasada')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($vencidos > 0 ? 'danger' : 'success'),

            Stat::make('Próximos 30 días', $proximos30)
                ->description('Pendientes en la ventana')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('info'),

            Stat::make('Cumplidos (este mes)', $cumplidosMes)
                ->description(now()->translatedFormat('F Y'))
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),

            Stat::make('No cumplidos (este mes)', $noCumplidosMes)
                ->description(now()->translatedFormat('F Y'))
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($noCumplidosMes > 0 ? 'danger' : 'gray'),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole([
            'super_admin', 'admin', 'supervisor_soporte', 'agente_soporte', 'tecnico_campo',
        ]) ?? false;
    }
}
