<?php

namespace App\Notifications;

use App\Models\Department;
use App\Models\Ticket;
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
        $mail = (new MailMessage)
            ->subject("[{$this->ticket->number}] Tu ticket fue trasladado a {$this->toDepartment->name}")
            ->greeting("Hola {$notifiable->name},")
            ->line("Tu ticket **{$this->ticket->number}** — *{$this->ticket->subject}* fue trasladado al departamento correcto para ser atendido.")
            ->line("De: {$this->fromDepartment->name}")
            ->line("A: {$this->toDepartment->name}");

        if ($this->reason) {
            $mail->line("Motivo del traslado: {$this->reason}");
        }

        return $mail
            ->action('Ver mi ticket', url("/portal/tickets/{$this->ticket->id}"))
            ->line('Seguirás recibiendo notificaciones cuando haya novedades.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->number,
            'from_department_id' => $this->fromDepartment->id,
            'from_department_name' => $this->fromDepartment->name,
            'to_department_id' => $this->toDepartment->id,
            'to_department_name' => $this->toDepartment->name,
            'reason' => $this->reason,
            'subject' => $this->ticket->subject,
        ];
    }
}
