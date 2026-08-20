<?php

namespace App\Notifications;

use App\Models\ScheduledMaintenance;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifica al agente que un mantenimiento se acerca a la fecha
 * programada (por defecto 30 días de anticipación).
 */
class ScheduledMaintenanceDueNotification extends Notification
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
        $days = (int) now()->startOfDay()->diffInDays($this->maintenance->scheduled_at, false);

        return (new MailMessage)
            ->subject("Mantenimiento próximo — {$tag}")
            ->greeting('Hola '.($notifiable->name ?? 'agente'))
            ->line($days > 0
                ? "Faltan {$days} días para el mantenimiento programado del activo {$tag}."
                : "El mantenimiento del activo {$tag} está programado para hoy.")
            ->line('Fecha: '.$date)
            ->action('Ver mantenimiento', url('/soporte/scheduled-maintenances/'.$this->maintenance->id.'/edit'));
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $asset = $this->maintenance->asset;
        $tag = $asset?->asset_tag ?: 'Activo #'.$asset?->id;
        $date = $this->maintenance->scheduled_at->translatedFormat('d M Y');
        $days = (int) now()->startOfDay()->diffInDays($this->maintenance->scheduled_at, false);

        $body = $days > 0
            ? "Faltan {$days} días · Activo {$tag} · programado para {$date}."
            : "Vence hoy · Activo {$tag}.";

        return FilamentNotification::make()
            ->title('Mantenimiento próximo a vencer')
            ->body($body)
            ->icon('heroicon-o-bell-alert')
            ->iconColor('warning')
            ->actions([
                Action::make('view')
                    ->label('Ver')
                    ->url('/soporte/scheduled-maintenances/'.$this->maintenance->id.'/edit'),
            ])
            ->getDatabaseMessage();
    }
}
