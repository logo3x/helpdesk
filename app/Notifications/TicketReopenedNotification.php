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

/**
 * Se dispara cuando un solicitante reabre su ticket (porque la
 * resolución no le funcionó).
 *
 * Va al agente asignado y al supervisor del depto del ticket, para
 * que sepan que tienen que re-trabajarlo. Si nadie estaba asignado,
 * solo notifica al supervisor.
 */
class TicketReopenedNotification extends Notification implements ShouldQueue
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
            ->subject("[{$this->ticket->number}] Ticket reabierto: {$this->ticket->subject}")
            ->greeting("Hola {$notifiable->name},")
            ->line('El solicitante reabrió este ticket — la resolución anterior no resolvió su problema:')
            ->line("**{$this->ticket->number}** — {$this->ticket->subject}")
            ->line("Prioridad: {$this->ticket->priority->getLabel()}")
            ->action('Ver ticket', $this->ticketUrlFor($notifiable, $this->ticket))
            ->line('Revisalo y dale seguimiento.');
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title("Ticket {$this->ticket->number} reabierto")
            ->body("{$this->ticket->subject} — el solicitante lo reabrió.")
            ->icon('heroicon-o-arrow-path')
            ->iconColor('warning')
            ->actions([
                Action::make('view')
                    ->label('Ver ticket')
                    ->url($this->ticketUrlFor($notifiable, $this->ticket))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
