<?php

namespace App\Jobs;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Services\KbSuggestionService;
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
        $system = app(KbSuggestionService::class)->systemUser();
        $template = (string) config('tickets.awaiting_customer.messages.resolved_closed');

        $days = (int) config('tickets.auto_close_days', 2);

        $tickets = Ticket::query()
            ->where('status', TicketStatus::Resuelto)
            ->where('resolved_at', '<=', now()->subDays($days))
            ->get();

        $count = 0;

        foreach ($tickets as $ticket) {
            // Mensaje visible en el portal explicando el cierre. No se
            // notifica aparte: la encuesta que dispara close() ya le llega.
            TicketComment::create([
                'ticket_id' => $ticket->id,
                'user_id' => $system->id,
                'body' => strtr($template, [':name' => $ticket->requester?->name ?? '', ':number' => $ticket->number]),
                'is_private' => false,
                'is_system_event' => true,
                'event_type' => 'auto_closed',
            ]);

            $ticketService->close($ticket);
            $count++;
        }

        if ($count > 0) {
            Log::channel('stack')->info("Auto-close: {$count} ticket(s) closed after {$days} days resolved.");
        }
    }
}
