<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\TicketComment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketCommentedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public TicketComment $comment,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[{$this->ticket->number}] Nuevo comentario en tu ticket")
            ->greeting("Hola {$notifiable->name},")
            ->line('Hay un nuevo comentario en el ticket:')
            ->line("**{$this->ticket->number}** — {$this->ticket->subject}")
            ->line("— {$this->comment->user->name} escribió:")
            ->line('> '.str($this->comment->body)->limit(200))
            ->action('Ver ticket', url("/portal/tickets/{$this->ticket->id}"));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->number,
            'subject' => $this->ticket->subject,
            'comment_by' => $this->comment->user->name,
            'type' => 'ticket_commented',
        ];
    }
}
