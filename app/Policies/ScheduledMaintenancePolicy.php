<?php

namespace App\Policies;

use App\Models\ScheduledMaintenance;
use App\Models\User;

/**
 * Autorización para el módulo Mantenimientos Programados.
 *
 * Reglas:
 *   - super_admin/admin: acceso total.
 *   - supervisor_soporte: acceso total (crear, editar, borrar) sobre
 *     los mtto de su departamento — el scope por departamento se
 *     aplica en getEloquentQuery() del Resource, esta policy solo
 *     valida por acción.
 *   - agente_soporte/tecnico_campo: ven y editan sus asignados; NO
 *     crean ni borran.
 *   - Otros roles: sin acceso.
 *
 * IMPORTANTE: la existencia de este policy INTERCEPTA cualquier
 * verificación previa de Filament Shield sobre este modelo. Antes,
 * Shield generaba un permiso auto-nombrado como `delete_scheduled::
 * maintenance` que ningún rol tenía asignado, resultando en 403 al
 * intentar borrar.
 */
class ScheduledMaintenancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            'super_admin', 'admin', 'supervisor_soporte',
            'agente_soporte', 'tecnico_campo',
        ]);
    }

    public function view(User $user, ScheduledMaintenance $maintenance): bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte'])) {
            return true;
        }

        return $user->hasAnyRole(['agente_soporte', 'tecnico_campo'])
            && $maintenance->agent_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte']);
    }

    public function update(User $user, ScheduledMaintenance $maintenance): bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte'])) {
            return true;
        }

        return $user->hasAnyRole(['agente_soporte', 'tecnico_campo'])
            && $maintenance->agent_id === $user->id;
    }

    public function delete(User $user, ScheduledMaintenance $maintenance): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte']);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte']);
    }

    public function restore(User $user, ScheduledMaintenance $maintenance): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']);
    }

    public function forceDelete(User $user, ScheduledMaintenance $maintenance): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']);
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']);
    }

    public function replicate(User $user, ScheduledMaintenance $maintenance): bool
    {
        return $this->update($user, $maintenance);
    }

    public function reorder(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte']);
    }
}
