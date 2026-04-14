<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[{$this->ticket->number}] Ticket asignado a ti")
            ->greeting("Hola {$notifiable->name},")
            ->line('Se te ha asignado el ticket:')
            ->line("**{$this->ticket->number}** — {$this->ticket->subject}")
            ->line("Prioridad: {$this->ticket->priority->getLabel()}")
            ->line("Solicitante: {$this->ticket->requester->name}")
            ->action('Atender ticket', url("/soporte/tickets/{$this->ticket->id}"))
            ->line('Por favor responde lo antes posible.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->number,
            'subject' => $this->ticket->subject,
            'type' => 'ticket_assigned',
        ];
    }
}
