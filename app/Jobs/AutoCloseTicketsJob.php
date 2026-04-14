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
 * Auto-close tickets that have been "resuelto" for more than 7 days
 * without the requester reopening them.
 */
class AutoCloseTicketsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 3600; // 1 hour

    public function handle(TicketService $ticketService): void
    {
        $tickets = Ticket::query()
            ->where('status', TicketStatus::Resuelto)
            ->where('resolved_at', '<=', now()->subDays(7))
            ->get();

        $count = 0;

        foreach ($tickets as $ticket) {
            $ticketService->close($ticket);
            $count++;
        }

        if ($count > 0) {
            Log::channel('stack')->info("Auto-close: {$count} ticket(s) closed after 7 days resolved.");
        }
    }
}
