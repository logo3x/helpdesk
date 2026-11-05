<?php

namespace App\Notifications;

use App\Enums\BackupType;
use App\Services\BackupManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Attachment;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

/**
 * Respaldo generado. Va a admin/super_admin (y BACKUP_NOTIFY_EMAILS).
 *
 * - El zip de BD se adjunta si pesa menos de BACKUP_MAIL_ATTACHMENT_MAX_MB
 *   (Graph limita el tamaño del correo); si no, se envía un link.
 * - El zip completo siempre va como link firmado y temporal, que además
 *   exige sesión de admin.
 */
class BackupCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public BackupType $type,
        public string $path,
        public int $size,
        public ?string $databasePath = null,
        public int $databaseSize = 0,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function shouldAttachDatabase(): bool
    {
        return $this->databasePath !== null
            && app(BackupManager::class)->canAttach($this->databaseSize)
            && app(BackupManager::class)->disk()->exists($this->databasePath);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $backups = app(BackupManager::class);
        $days = (int) config('backup.helpdesk.download_link_days', 7);
        $attachDatabase = $this->shouldAttachDatabase();

        $message = (new MailMessage)
            ->subject($this->type === BackupType::Full
                ? 'Respaldo semanal completo generado — Helpdesk'
                : 'Respaldo diario de la base de datos generado — Helpdesk')
            ->greeting('Hola '.($notifiable->name ?? 'equipo').',')
            ->line($this->type === BackupType::Full
                ? 'Se generó el respaldo completo (archivos + base de datos) de esta semana.'
                : 'Se generó el respaldo diario de la base de datos.');

        if ($this->type === BackupType::Full) {
            $message
                ->line('**Completo:** '.Str::afterLast($this->path, '/').' ('.Number::fileSize($this->size, precision: 1).')')
                ->action('Descargar respaldo completo', $backups->downloadUrl($this->path));
        }

        if ($this->databasePath !== null) {
            $databaseLine = '**Base de datos:** '.Str::afterLast($this->databasePath, '/').' ('.Number::fileSize($this->databaseSize, precision: 1).')';

            if ($attachDatabase) {
                $message->line($databaseLine.'. Va adjunto a este correo.');
            } else {
                $message
                    ->line($databaseLine.'. Pesa más de '.config('backup.helpdesk.attachment_max_mb').' MB, así que no se adjuntó.')
                    ->line('[Descargar respaldo de la base de datos]('.$backups->downloadUrl($this->databasePath).')');
            }
        }

        $message->line("Los links vencen en {$days} días y solo funcionan con una sesión de administrador iniciada.");

        if (filled(config('backup.backup.password'))) {
            $message->line('Los zips están protegidos con la contraseña de respaldos: ábrelos con 7-Zip o WinRAR.');
        } else {
            $message->line('Aviso: los zips no tienen contraseña. Define BACKUP_ARCHIVE_PASSWORD para cifrarlos.');
        }

        if ($attachDatabase) {
            $message->attach(
                Attachment::fromStorageDisk($backups->diskName(), $this->databasePath)
                    ->as(Str::afterLast($this->databasePath, '/'))
                    ->withMime('application/zip')
            );
        }

        return $message;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type->value,
            'path' => $this->path,
            'size' => $this->size,
            'database_path' => $this->databasePath,
        ];
    }
}
