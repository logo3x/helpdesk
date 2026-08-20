<?php

namespace App\Notifications;

use App\Models\ScheduledMaintenance;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifica al agente que se le asignó un mantenimiento programado.
 * Canales: database (campanita Filament) + mail (si SMTP disponible).
 */
class ScheduledMaintenanceAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(public ScheduledMaintenance $maintenance) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $asset = $this->maintenance->asset;
        $tag = $asset?->asset_tag ?: 'Activo #'.$asset?->id;
        $date = $this->maintenance->scheduled_at->translatedFormat('d M Y');

        return (new MailMessage)
            ->subject("Mantenimiento asignado — {$tag}")
            ->greeting('Hola '.($notifiable->name ?? 'agente'))
            ->line("Se te asignó un mantenimiento programado sobre el activo {$tag}.")
            ->line('Fecha programada: '.$date)
            ->line('Frecuencia: '.$this->maintenance->frequency->label())
            ->action('Ver mantenimiento', url('/soporte/scheduled-maintenances/'.$this->maintenance->id.'/edit'))
            ->line('Recordá registrar las observaciones al ejecutarlo.');
    }

    /**
     * Formato database para la campanita Filament. Guardamos title, body
     * y una URL de acción que Filament renderiza como link.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $asset = $this->maintenance->asset;
        $tag = $asset?->asset_tag ?: 'Activo #'.$asset?->id;
        $date = $this->maintenance->scheduled_at->translatedFormat('d M Y');

        return FilamentNotification::make()
            ->title('Mantenimiento asignado')
            ->body("Activo {$tag} — programado para {$date}.")
            ->icon('heroicon-o-wrench-screwdriver')
            ->iconColor('info')
            ->actions([
                Action::make('view')
                    ->label('Ver')
                    ->url('/soporte/scheduled-maintenances/'.$this->maintenance->id.'/edit'),
            ])
            ->getDatabaseMessage();
    }
}
