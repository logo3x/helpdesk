<?php

namespace App\Filament\Soporte\Widgets;

use App\Enums\MaintenanceStatus;
use App\Models\ScheduledMaintenance;
use App\Models\Ticket;
use Filament\Widgets\Widget;

class WelcomeWidget extends Widget
{
    protected string $view = 'filament.soporte.widgets.welcome-widget';

    protected static ?int $sort = -10;

    protected int|string|array $columnSpan = 'full';

    public function getViewData(): array
    {
        $user = auth()->user();
        $hour = now()->hour;

        $greeting = match (true) {
            $hour < 12 => 'Buenos días',
            $hour < 18 => 'Buenas tardes',
            default => 'Buenas noches',
        };

        $firstName = explode(' ', (string) $user?->name)[0] ?? '';

        // Aplica el mismo scope de depto que el listado — antes contaba
        // sin filtrar por depto y aparecían tickets que después el
        // listado escondía (bug reportado por agente 2026-08-24).
        $myOpenQuery = Ticket::query()
            ->where('assigned_to_id', $user?->id)
            ->whereNotIn('status', ['resuelto', 'cerrado']);

        if ($user && ! $user->hasAnyRole(['super_admin', 'admin']) && $user->department_id) {
            $myOpenQuery->where('department_id', $user->department_id);
        }

        $myOpen = $myOpenQuery->count();

        // Mantenimientos programados asignados al usuario logueado.
        // Solo pendientes — ya cumplidos/no cumplidos no cuentan.
        // Overdue = pendientes cuya fecha ya pasó.
        $myMaintenancesQuery = ScheduledMaintenance::query()
            ->where('agent_id', $user?->id)
            ->where('status', MaintenanceStatus::Pendiente->value);

        $myMaintenancesOpen = (clone $myMaintenancesQuery)->count();
        $myMaintenancesOverdue = (clone $myMaintenancesQuery)
            ->where('scheduled_at', '<', now()->startOfDay())
            ->count();

        $roles = $user?->getRoleNames()->implode(', ') ?? '';
        $roleLabel = match (true) {
            str_contains($roles, 'super_admin') => 'Super Administrador',
            str_contains($roles, 'admin') => 'Administrador',
            str_contains($roles, 'supervisor') => 'Supervisor de Soporte',
            str_contains($roles, 'agente') => 'Agente de Soporte',
            default => 'Usuario',
        };

        // ¿El usuario puede ver el módulo de mantenimientos? El widget de
        // mantenimientos solo lo mostramos si tiene rol operativo. Super
        // admin/admin ya tienen KPIs dedicados en el listado.
        $showMaintenances = $user?->hasAnyRole([
            'super_admin', 'admin', 'supervisor_soporte',
            'agente_soporte', 'tecnico_campo',
        ]) ?? false;

        return [
            'greeting' => $greeting,
            'firstName' => $firstName,
            'fullName' => $user?->name,
            'roleLabel' => $roleLabel,
            'myOpen' => $myOpen,
            'myMaintenancesOpen' => $myMaintenancesOpen,
            'myMaintenancesOverdue' => $myMaintenancesOverdue,
            'showMaintenances' => $showMaintenances,
            'avatarUrl' => null,
        ];
    }
}
