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

class TicketCreatedNotification extends Notification implements ShouldQueue
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
            ->subject("[{$this->ticket->number}] Ticket creado: {$this->ticket->subject}")
            ->greeting("Hola {$notifiable->name},")
            ->line('Se ha creado un nuevo ticket:')
            ->line("**{$this->ticket->number}** — {$this->ticket->subject}")
            ->line("Prioridad: {$this->ticket->priority->getLabel()}")
            ->action('Ver ticket', url("/portal/tickets/{$this->ticket->id}"))
            ->line('Recibirás actualizaciones sobre el progreso.');
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title("Ticket {$this->ticket->number} creado")
            ->body("{$this->ticket->subject} · Prioridad {$this->ticket->priority->getLabel()}")
            ->icon('heroicon-o-ticket')
            ->iconColor('success')
            ->actions([
                Action::make('view')
                    ->label('Ver ticket')
                    ->url($this->ticketUrlFor($notifiable, $this->ticket))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
