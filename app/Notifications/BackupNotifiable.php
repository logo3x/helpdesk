<?php

namespace App\Notifications;

use App\Models\User;
use Spatie\Backup\Notifications\Notifiable as SpatieNotifiable;

class BackupNotifiable extends SpatieNotifiable
{
    /**
     * Envía notificaciones de backup a todos los admins y super_admins.
     *
     * @return array<int, string>
     */
    public function routeNotificationForMail(): array
    {
        return User::role(['super_admin', 'admin'])
            ->pluck('email')
            ->filter()
            ->values()
            ->all();
    }
}
