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
 * que su problema se considera resuelto y que el ticket se cerrará solo
 * a los config('tickets.auto_close_days') días. El portal no permite
 * reabrir, así que si el problema sigue se le pide crear un ticket nuevo
 * con el número anterior.
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
            ->line("Si no hay novedades, el ticket se cerrará automáticamente {$this->autoCloseLabel()} después y te enviaremos una encuesta corta.")
            ->line("Si la solución no resolvió tu problema, crea un ticket nuevo desde el portal e indica el número **{$this->ticket->number}**.")
            ->action('Ver ticket', $this->ticketUrlFor($notifiable, $this->ticket))
            ->line('Gracias por usar el helpdesk.');
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title("Ticket {$this->ticket->number} resuelto")
            ->body("{$this->ticket->subject} — se cerrará automáticamente {$this->autoCloseLabel()} después. Si no quedó resuelto, crea un ticket nuevo con este número.")
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

    /**
     * Plazo del cierre automático de tickets resueltos ("2 días", "1 día").
     */
    protected function autoCloseLabel(): string
    {
        $days = (int) config('tickets.auto_close_days');

        return $days === 1 ? '1 día' : "{$days} días";
    }
}
