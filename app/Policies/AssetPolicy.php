<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Asset;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Authorization for Asset (inventario).
 *
 * Las reglas se derivan del rol y del flag `can_access_inventory` del
 * departamento del usuario, no de permisos Shield. Esto permite que el
 * admin habilite el módulo de inventario por depto desde la UI sin
 * tener que regenerar permisos cada vez.
 *
 *   - super_admin / admin: acceso total siempre.
 *   - supervisor_soporte / tecnico_campo: crear, editar y ver si su
 *     depto tiene `can_access_inventory = true`. Borrar solo
 *     supervisor.
 *   - agente_soporte: SOLO LECTURA (ver listado y ficha) cuando su
 *     depto tiene `can_access_inventory = true`. No crear/editar/borrar
 *     — el agente consulta el inventario para resolver tickets.
 *   - usuario_final: nunca.
 */
class AssetPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $user): bool
    {
        return $this->hasInventoryAccess($user);
    }

    public function view(AuthUser $user, Asset $asset): bool
    {
        return $this->hasInventoryAccess($user);
    }

    public function create(AuthUser $user): bool
    {
        return $this->canWriteInventory($user);
    }

    public function update(AuthUser $user, Asset $asset): bool
    {
        return $this->canWriteInventory($user);
    }

    public function delete(AuthUser $user, Asset $asset): bool
    {
        // Borrar requiere ser supervisor+ aunque tenga acceso al módulo:
        // un agente normal no debería poder borrar inventario, solo
        // editarlo o cambiar status a "retirado".
        if ($user->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte'])) {
            return $this->hasInventoryAccess($user);
        }

        return false;
    }

    public function deleteAny(AuthUser $user): bool
    {
        return $this->delete($user, new Asset);
    }

    public function restore(AuthUser $user, Asset $asset): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']);
    }

    public function forceDelete(AuthUser $user, Asset $asset): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']);
    }

    public function forceDeleteAny(AuthUser $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']);
    }

    public function restoreAny(AuthUser $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']);
    }

    public function replicate(AuthUser $user, Asset $asset): bool
    {
        return $this->update($user, $asset);
    }

    public function reorder(AuthUser $user): bool
    {
        return $this->canWriteInventory($user);
    }

    /**
     * Regla de lectura: super_admin/admin pasan siempre; supervisor,
     * técnico y agente solo si su depto tiene `can_access_inventory`.
     * usuario_final y editor_kb nunca.
     */
    protected function hasInventoryAccess(AuthUser $user): bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return true;
        }

        if (! $user->department?->can_access_inventory) {
            return false;
        }

        return $user->hasAnyRole(['supervisor_soporte', 'tecnico_campo', 'agente_soporte']);
    }

    /**
     * Regla de escritura: solo supervisor_soporte y tecnico_campo, no
     * agente_soporte. Agente solo lee.
     */
    protected function canWriteInventory(AuthUser $user): bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return true;
        }

        if (! $user->department?->can_access_inventory) {
            return false;
        }

        return $user->hasAnyRole(['supervisor_soporte', 'tecnico_campo']);
    }
}
