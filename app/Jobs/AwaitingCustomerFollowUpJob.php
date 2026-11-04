<?php

namespace App\Jobs;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Notifications\TicketCommentedNotification;
use App\Services\KbSuggestionService;
use App\Services\SlaService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Seguimiento de tickets en "Pendiente cliente" (SLA en pausa):
 * recordatorios en las horas hábiles configuradas y cierre automático si
 * el cliente nunca responde. Ver config/tickets.php → awaiting_customer.
 */
class AwaitingCustomerFollowUpJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 600;

    public const REMINDER_EVENT = 'customer_reminder';

    public const CLOSED_EVENT = 'closed_no_response';

    public function handle(KbSuggestionService $suggestions, SlaService $sla): void
    {
        $config = config('tickets.awaiting_customer');
        $reminderHours = collect($config['reminder_hours'] ?? [])->sort()->values();
        $closeHours = (int) ($config['close_hours'] ?? 10);
        $system = $suggestions->systemUser();
        $closed = 0;
        $reminded = 0;

        Ticket::query()
            ->where('status', TicketStatus::PendienteCliente)
            ->whereNotNull('paused_at')
            ->with('requester')
            ->each(function (Ticket $ticket) use ($config, $reminderHours, $closeHours, $system, $sla, &$closed, &$reminded): void {
                $businessHours = $sla->businessMinutesBetween($ticket->paused_at, now()) / 60;

                if ($businessHours >= $closeHours) {
                    $this->comment($ticket, $system->id, self::CLOSED_EVENT, $config['messages']['closed'], 0);

                    // Sin encuesta: el ticket no se resolvió, se cerró por
                    // falta de respuesta.
                    $ticket->forceFill([
                        'status' => TicketStatus::Cerrado,
                        'resolved_at' => $ticket->resolved_at ?? now(),
                        'closed_at' => now(),
                    ])->save();

                    $closed++;

                    return;
                }

                $remindersSent = $ticket->comments()
                    ->where('event_type', self::REMINDER_EVENT)
                    ->where('created_at', '>=', $ticket->paused_at)
                    ->count();

                $due = $reminderHours->filter(fn (int $hour): bool => $businessHours >= $hour)->count();

                if ($due > $remindersSent) {
                    $isLast = $due === $reminderHours->count();
                    $this->comment(
                        $ticket,
                        $system->id,
                        self::REMINDER_EVENT,
                        $config['messages'][$isLast ? 'last_reminder' : 'reminder'],
                        max(1, (int) ceil($closeHours - $businessHours)),
                    );
                    $reminded++;
                }
            });

        if ($closed + $reminded > 0) {
            Log::info("Seguimiento pendiente cliente: {$reminded} recordatorio(s), {$closed} cierre(s).");
        }
    }

    /**
     * Comentario público del sistema + aviso al cliente (campanita y correo).
     */
    protected function comment(Ticket $ticket, int $systemUserId, string $event, string $template, int $hoursLeft): void
    {
        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $systemUserId,
            'body' => strtr($template, [
                ':name' => $ticket->requester?->name ?? '',
                ':number' => $ticket->number,
                ':hours' => (string) $hoursLeft,
            ]),
            'is_private' => false,
            'is_system_event' => true,
            'event_type' => $event,
        ]);

        $ticket->requester?->notify(new TicketCommentedNotification($ticket, $comment));
    }
}
