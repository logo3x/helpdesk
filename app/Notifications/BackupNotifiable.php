<?php

namespace App\Notifications;

use App\Models\User;
use Spatie\Backup\Notifications\Notifiable as SpatieNotifiable;

class BackupNotifiable extends SpatieNotifiable
{
    /**
     * Envía las alertas de spatie (respaldo no sano, limpieza fallida) a
     * todos los admins y super_admins, más BACKUP_NOTIFY_EMAILS.
     *
     * @return array<int, string>
     */
    public function routeNotificationForMail(): array
    {
        return User::role(['super_admin', 'admin'])
            ->pluck('email')
            ->merge(config('backup.helpdesk.notify_emails', []))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
