<?php

namespace App\Notifications;

use App\Models\Department;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Se envía a los supervisores del departamento DESTINO cuando un ticket
 * les es trasladado. Diferente de TicketTransferredNotification (esa va
 * al solicitante). Esta notifica al equipo receptor para que sepan que
 * tienen un ticket nuevo en su cola.
 */
class TicketReceivedFromTransferNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public Department $fromDepartment,
        public ?string $reason = null,
        public ?string $transferredBy = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $sanitize = fn (?string $s): string => trim(strip_tags((string) $s));

        $subject = $sanitize($this->ticket->subject);
        $reason = $this->reason ? $sanitize($this->reason) : null;
        $fromName = $sanitize($this->fromDepartment->name);
        $by = $this->transferredBy ? $sanitize($this->transferredBy) : 'un supervisor';

        $mail = (new MailMessage)
            ->subject("[{$this->ticket->number}] Ticket trasladado a tu departamento")
            ->greeting("Hola {$sanitize($notifiable->name)},")
            ->line("El ticket **{$this->ticket->number}** — *{$subject}* fue trasladado a tu departamento.")
            ->line("Viene de: **{$fromName}**")
            ->line("Trasladado por: {$by}");

        if ($reason) {
            $mail->line("Motivo: {$reason}");
        }

        return $mail
            ->line('El ticket aparece sin asignar en tu cola. Por favor asígnalo a un agente o tómalo directamente.')
            ->action('Ver ticket', url("/soporte/tickets/{$this->ticket->id}"));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->number,
            'from_department_id' => $this->fromDepartment->id,
            'from_department_name' => $this->fromDepartment->name,
            'reason' => $this->reason,
            'transferred_by' => $this->transferredBy,
            'subject' => $this->ticket->subject,
        ];
    }
}
