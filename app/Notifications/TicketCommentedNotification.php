<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Notifications\Concerns\BuildsFilamentDatabasePayload;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketCommentedNotification extends Notification implements ShouldQueue
{
    use BuildsFilamentDatabasePayload;
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
    public function toDatabase(object $notifiable): array
    {
        $author = $this->comment->user->name;
        $preview = (string) str($this->comment->body)->limit(120);

        return FilamentNotification::make()
            ->title("Nuevo comentario en {$this->ticket->number}")
            ->body("{$author}: {$preview}")
            ->icon('heroicon-o-chat-bubble-left-right')
            ->iconColor('info')
            ->actions([
                Action::make('view')
                    ->label('Ver ticket')
                    ->url($this->ticketUrlFor($notifiable, $this->ticket))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
