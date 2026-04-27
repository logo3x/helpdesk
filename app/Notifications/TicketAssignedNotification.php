<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Notifications\Concerns\BuildsFilamentDatabasePayload;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketAssignedNotification extends Notification implements ShouldQueue
{
    use BuildsFilamentDatabasePayload;
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
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title("Ticket {$this->ticket->number} asignado a ti")
            ->body("{$this->ticket->subject} · Prioridad {$this->ticket->priority->getLabel()}")
            ->icon('heroicon-o-user-plus')
            ->iconColor('info')
            ->actions([
                Action::make('view')
                    ->label('Atender ticket')
                    ->url($this->ticketUrlFor($notifiable, $this->ticket))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
