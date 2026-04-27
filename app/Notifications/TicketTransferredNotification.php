<?php

namespace App\Notifications;

use App\Models\Department;
use App\Models\Ticket;
use App\Notifications\Concerns\BuildsFilamentDatabasePayload;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the ticket requester when a supervisor transfers their
 * ticket to a different department.
 */
class TicketTransferredNotification extends Notification implements ShouldQueue
{
    use BuildsFilamentDatabasePayload;
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public Department $fromDepartment,
        public Department $toDepartment,
        public ?string $reason = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Sanitizar textos de usuario (subject, reason) para evitar markdown
        // injection en el email (links maliciosos, imágenes remotas para
        // tracking, etc.). `->line()` interpreta Markdown por default.
        $sanitize = fn (?string $s): string => trim(strip_tags((string) $s));

        $subject = $sanitize($this->ticket->subject);
        $reason = $this->reason ? $sanitize($this->reason) : null;
        $ticketNumber = $this->ticket->number;
        $fromName = $sanitize($this->fromDepartment->name);
        $toName = $sanitize($this->toDepartment->name);

        $mail = (new MailMessage)
            ->subject("[{$ticketNumber}] Tu ticket fue trasladado a {$toName}")
            ->greeting("Hola {$sanitize($notifiable->name)},")
            ->line("Tu ticket **{$ticketNumber}** — *{$subject}* fue trasladado al departamento correcto para ser atendido.")
            ->line("De: {$fromName}")
            ->line("A: {$toName}");

        if ($reason) {
            $mail->line("Motivo del traslado: {$reason}");
        }

        return $mail
            ->action('Ver mi ticket', url("/portal/tickets/{$this->ticket->id}"))
            ->line('Seguirás recibiendo notificaciones cuando haya novedades.');
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $sanitize = fn (?string $s): string => trim(strip_tags((string) $s));
        $to = $sanitize($this->toDepartment->name);
        $from = $sanitize($this->fromDepartment->name);

        return FilamentNotification::make()
            ->title("Tu ticket {$this->ticket->number} fue trasladado a {$to}")
            ->body("De {$from} a {$to}. Sigue abierto y será atendido por el nuevo equipo.")
            ->icon('heroicon-o-arrow-right-circle')
            ->iconColor('warning')
            ->actions([
                Action::make('view')
                    ->label('Ver mi ticket')
                    ->url($this->ticketUrlFor($notifiable, $this->ticket))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
