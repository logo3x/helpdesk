<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TicketComment;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Authorization for TicketComment.
 *
 * No usamos Shield permissions aquí: las reglas se derivan del ticket
 * padre. Si el usuario puede ver el ticket → puede ver/crear comentarios
 * en él. Update/delete solo del autor del comentario (o admin).
 *
 * Esto evita tener que mantener un permiso Shield duplicado para cada
 * acción y mantiene una sola fuente de verdad: TicketPolicy.
 */
class TicketCommentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $user): bool
    {
        // viewAny en RelationManager se llama sin contexto del ticket
        // padre; cualquier rol que pueda llegar a una página de ticket
        // tiene scope por TicketPolicy::view, así que aquí permitimos.
        return true;
    }

    public function view(AuthUser $user, TicketComment $comment): bool
    {
        return $user->can('view', $comment->ticket);
    }

    public function create(AuthUser $user): bool
    {
        // El RelationManager pasa el ticket padre por el ownerRecord,
        // pero Filament llama a create() sin el modelo del comment
        // todavía existente. Permitimos a cualquiera que tenga rol de
        // soporte/admin/usuario_final; la restricción real por ticket
        // la aplica TicketPolicy::view (que se llama antes de abrir
        // la página). Negar aquí solo bloquearía el botón en la UI.
        return $user->hasAnyRole([
            'super_admin',
            'admin',
            'supervisor_soporte',
            'agente_soporte',
            'tecnico_campo',
            'usuario_final',
        ]);
    }

    public function update(AuthUser $user, TicketComment $comment): bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return true;
        }

        return $comment->user_id === $user->id;
    }

    public function delete(AuthUser $user, TicketComment $comment): bool
    {
        return $this->update($user, $comment);
    }

    public function restore(AuthUser $user, TicketComment $comment): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']);
    }

    public function forceDelete(AuthUser $user, TicketComment $comment): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']);
    }
}
