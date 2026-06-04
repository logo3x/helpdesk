<?php

namespace App\Observers;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Services\SlaService;

/**
 * Mantiene el reloj de pausa del SLA en sync con el status del ticket.
 *
 * La pausa NO se podía depender de que el dev llamara `TicketService::pauseSla()`
 * a mano — el status se cambia desde varios lugares (EditAction de Filament,
 * acciones del portal, jobs auto-close). El observer asegura que SIEMPRE
 * que un ticket entra a `pendiente_cliente` arranque `paused_at`, y que
 * cuando sale, acumule los minutos en `paused_minutes`.
 *
 * Solo se llama al guardar (updating event para tener acceso al status viejo
 * vía $ticket->getOriginal()).
 */
class TicketObserver
{
    public function updating(Ticket $ticket): void
    {
        if (! $ticket->isDirty('status')) {
            return;
        }

        $previousStatus = $ticket->getOriginal('status');
        $newStatus = $ticket->status;

        // Normalize: getOriginal puede devolver el string crudo de BD
        // mientras que $ticket->status ya está casteado al enum.
        $previousEnum = $previousStatus instanceof TicketStatus
            ? $previousStatus
            : ($previousStatus !== null ? TicketStatus::tryFrom((string) $previousStatus) : null);

        $enteringPause = $newStatus === TicketStatus::PendienteCliente
            && $previousEnum !== TicketStatus::PendienteCliente;

        $leavingPause = $previousEnum === TicketStatus::PendienteCliente
            && $newStatus !== TicketStatus::PendienteCliente;

        if ($enteringPause && $ticket->paused_at === null) {
            $ticket->paused_at = now();
        }

        if ($leavingPause && $ticket->paused_at !== null) {
            $pausedBizMinutes = app(SlaService::class)
                ->businessMinutesBetween($ticket->paused_at, now());

            $ticket->paused_minutes = ((int) $ticket->paused_minutes) + $pausedBizMinutes;
            $ticket->paused_at = null;
        }
    }
}
