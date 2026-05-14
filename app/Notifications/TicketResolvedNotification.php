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
 * Se dispara cuando un agente marca un ticket como Resuelto.
 *
 * Va al solicitante (notifiable) con un mensaje cortés explicando
 * que su problema se considera resuelto. Si no fue así, le invitamos
 * a reabrir el ticket desde el portal — el botón con la URL de
 * detalle queda incluido en el email/notificación.
 */
class TicketResolvedNotification extends Notification implements ShouldQueue
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
            ->subject("[{$this->ticket->number}] Ticket resuelto: {$this->ticket->subject}")
            ->greeting("Hola {$notifiable->name},")
            ->line('Tu ticket fue marcado como **resuelto** por el equipo de soporte:')
            ->line("**{$this->ticket->number}** — {$this->ticket->subject}")
            ->line('Si la solución no resolvió tu problema, puedes reabrir el ticket desde el portal.')
            ->action('Ver ticket', $this->ticketUrlFor($notifiable, $this->ticket))
            ->line('Gracias por usar el helpdesk.');
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title("Ticket {$this->ticket->number} resuelto")
            ->body("{$this->ticket->subject} — si no quedó resuelto, podés reabrirlo desde el portal.")
            ->icon('heroicon-o-check-circle')
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
