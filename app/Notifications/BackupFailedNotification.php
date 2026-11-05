<?php

namespace App\Notifications;

use App\Enums\BackupType;
use App\Filament\Pages\Backups;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Throwable;

/**
 * Alerta a admin/super_admin cuando un respaldo falla.
 */
class BackupFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public BackupType $type,
        public string $error,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject("Falló el respaldo {$this->type->label()} — Helpdesk")
            ->greeting('Hola '.($notifiable->name ?? 'equipo').',')
            ->line("El respaldo {$this->type->label()} del ".now()->format('d/m/Y H:i').' no se pudo generar.')
            ->line('**Detalle:** '.$this->error)
            ->line('Revisa storage/logs/laravel.log, que mysqldump esté en DB_DUMP_BINARY_PATH y que haya espacio en el disco de respaldos.')
            ->action('Abrir respaldos', $this->pageUrl());
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type->value,
            'error' => $this->error,
        ];
    }

    protected function pageUrl(): string
    {
        try {
            return Backups::getUrl(panel: 'admin');
        } catch (Throwable) {
            return url('/admin');
        }
    }
}
