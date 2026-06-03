<?php

namespace App\Jobs;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Auto-close tickets resueltos sin actividad por más de N días. N
 * viene de config/tickets.php (`auto_close_days`, default 7). Si el
 * solicitante reabre/comenta antes, el estado cambia y el ticket sale
 * del filtro.
 */
class AutoCloseTicketsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 3600; // 1 hour

    public function handle(TicketService $ticketService): void
    {
        $days = (int) config('tickets.auto_close_days', 7);

        $tickets = Ticket::query()
            ->where('status', TicketStatus::Resuelto)
            ->where('resolved_at', '<=', now()->subDays($days))
            ->get();

        $count = 0;

        foreach ($tickets as $ticket) {
            $ticketService->close($ticket);
            $count++;
        }

        if ($count > 0) {
            Log::channel('stack')->info("Auto-close: {$count} ticket(s) closed after {$days} days resolved.");
        }
    }
}
