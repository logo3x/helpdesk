<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KactusSyncFailedNotification extends Notification
{
    use Queueable;

    public function __construct(public string $reason) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Sync de Kactus falló — Helpdesk')
            ->greeting('Hola '.($notifiable->name ?? 'admin').',')
            ->line('El sync programado de Kactus falló con el siguiente detalle:')
            ->line($this->reason)
            ->line('Revisá storage/logs/laravel.log para más contexto.')
            ->action('Abrir panel admin', url('/admin/users'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'reason' => $this->reason,
            'failed_at' => now()->toIso8601String(),
        ];
    }
}
