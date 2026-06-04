<?php

namespace App\Console\Commands;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * One-shot para arrancar el reloj de pausa en tickets que YA estaban
 * en pendiente_cliente cuando se desplegó el TicketObserver.
 *
 * Sin esto, el observer no se entera de la pausa (no hubo evento
 * "updating") y el paused_minutes nunca crece.
 *
 * Setea `paused_at = now()` en todos los tickets `pendiente_cliente`
 * con paused_at NULL. Idempotente.
 */
#[Signature('tickets:backfill-paused')]
#[Description('Arranca el reloj de pausa en tickets ya en pendiente_cliente (one-shot post-deploy)')]
class BackfillPausedTickets extends Command
{
    public function handle(): int
    {
        $affected = Ticket::query()
            ->where('status', TicketStatus::PendienteCliente)
            ->whereNull('paused_at')
            ->update(['paused_at' => now()]);

        $this->info("Backfill listo: {$affected} ticket(s) en pendiente_cliente arrancaron su reloj de pausa.");

        return self::SUCCESS;
    }
}
