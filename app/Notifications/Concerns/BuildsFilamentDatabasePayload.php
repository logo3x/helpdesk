<?php

namespace App\Notifications\Concerns;

use App\Models\Ticket;

/**
 * Utilidades compartidas por las notificaciones que guardan en la
 * tabla `notifications` para que el popover de Filament (campana) les
 * pueda poner un botón "Ver ticket" con la URL correcta según el rol.
 *
 * Todas las notifications del dominio ticket devuelven desde su
 * `toDatabase()` el resultado de `Filament\Notifications\Notification::make()
 * ->...->getDatabaseMessage()`, lo que garantiza que el `data` JSON
 * tenga el shape que Filament espera (title, body, icon, iconColor,
 * actions[], duration='persistent', format='filament').
 */
trait BuildsFilamentDatabasePayload
{
    /**
     * Resuelve la URL al detalle del ticket según el rol del receptor.
     *
     * - Staff (admin/supervisor/agente/técnico) → /soporte/tickets/{id}
     * - Usuario final (o cualquier otro) → /portal/tickets/{id}
     *
     * Esto evita que un agente reciba un link al portal de solicitante
     * (sin acciones) y que un solicitante reciba un link al panel de
     * soporte (donde no entra).
     */
    protected function ticketUrlFor(object $notifiable, Ticket $ticket): string
    {
        $isStaff = method_exists($notifiable, 'hasAnyRole')
            && $notifiable->hasAnyRole([
                'super_admin',
                'admin',
                'supervisor_soporte',
                'agente_soporte',
                'tecnico_campo',
            ]);

        $path = $isStaff ? '/soporte' : '/portal';

        return url("{$path}/tickets/{$ticket->id}");
    }
}
