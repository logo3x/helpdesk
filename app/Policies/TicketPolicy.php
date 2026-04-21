<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Ticket;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Authorization policy for tickets.
 *
 * Combina permisos Shield con reglas de scope por departamento y
 * propiedad. Super_admin y admin pasan cualquier check (bypass
 * implícito en el provider de Shield).
 */
class TicketPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Ticket');
    }

    public function view(AuthUser $authUser, Ticket $ticket): bool
    {
        if (! $authUser->can('View:Ticket')) {
            return false;
        }

        return $this->canAccessTicket($authUser, $ticket);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Ticket');
    }

    public function update(AuthUser $authUser, Ticket $ticket): bool
    {
        if (! $authUser->can('Update:Ticket')) {
            return false;
        }

        return $this->canAccessTicket($authUser, $ticket);
    }

    public function delete(AuthUser $authUser, Ticket $ticket): bool
    {
        if (! $authUser->can('Delete:Ticket')) {
            return false;
        }

        return $this->canAccessTicket($authUser, $ticket);
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Ticket');
    }

    public function restore(AuthUser $authUser, Ticket $ticket): bool
    {
        return $authUser->can('Restore:Ticket') && $this->canAccessTicket($authUser, $ticket);
    }

    public function forceDelete(AuthUser $authUser, Ticket $ticket): bool
    {
        return $authUser->can('ForceDelete:Ticket') && $this->canAccessTicket($authUser, $ticket);
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Ticket');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Ticket');
    }

    public function replicate(AuthUser $authUser, Ticket $ticket): bool
    {
        return $authUser->can('Replicate:Ticket') && $this->canAccessTicket($authUser, $ticket);
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Ticket');
    }

    /**
     * Solo super_admin, admin y supervisor_soporte pueden trasladar
     * un ticket a otro departamento.
     */
    public function transfer(AuthUser $authUser, Ticket $ticket): bool
    {
        return $authUser->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte'])
            && $this->canAccessTicket($authUser, $ticket);
    }

    /**
     * Reglas de acceso transversales (view/update/delete/…):
     *
     * - super_admin / admin: acceso total.
     * - supervisor_soporte: tickets de su propio depto.
     * - agente_soporte / tecnico_campo: tickets de su depto Y asignados
     *   a él o sin asignar.
     * - usuario_final: solo sus propios tickets (requester_id).
     * - Sin rol: denegado.
     */
    protected function canAccessTicket(AuthUser $authUser, Ticket $ticket): bool
    {
        if ($authUser->hasAnyRole(['super_admin', 'admin'])) {
            return true;
        }

        // Usuario final: solo sus propios tickets.
        if ($authUser->hasRole('usuario_final')) {
            return $ticket->requester_id === $authUser->id;
        }

        // Roles de soporte: requieren depto coincidente.
        if (! $authUser->department_id || $ticket->department_id !== $authUser->department_id) {
            return false;
        }

        // Supervisor: todo el depto.
        if ($authUser->hasRole('supervisor_soporte')) {
            return true;
        }

        // Agente / técnico: sus asignados o sin asignar.
        return $ticket->assigned_to_id === $authUser->id
            || $ticket->assigned_to_id === null;
    }
}
