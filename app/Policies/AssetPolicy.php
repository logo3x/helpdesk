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
 *   - supervisor / agente / técnico: acceden si su depto tiene el flag
 *     `can_access_inventory = true`.
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
        return $this->hasInventoryAccess($user);
    }

    public function update(AuthUser $user, Asset $asset): bool
    {
        return $this->hasInventoryAccess($user);
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
        return $this->hasInventoryAccess($user);
    }

    /**
     * Regla central: super_admin/admin pasan siempre; el resto solo si
     * su departamento tiene `can_access_inventory = true`.
     */
    protected function hasInventoryAccess(AuthUser $user): bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return true;
        }

        return (bool) ($user->department?->can_access_inventory);
    }
}
