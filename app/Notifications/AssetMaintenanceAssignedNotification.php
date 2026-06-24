<?php

namespace App\Notifications;

use App\Models\Asset;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificación al agente/técnico cuando se le asigna la responsabilidad
 * de un mantenimiento (ya sea desde el form del activo o desde el modal
 * "Registrar evento → Mantenimiento").
 */
class AssetMaintenanceAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Asset $asset,
        public readonly User $assignedBy,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $label = $this->asset->asset_tag ?? $this->asset->hostname ?? "Activo #{$this->asset->id}";
        $nextDate = $this->asset->next_maintenance_at?->translatedFormat('d/m/Y') ?? 'Por definir';
        $interval = $this->asset->maintenance_interval_days
            ? "cada {$this->asset->maintenance_interval_days} días"
            : null;
        $custodio = $this->asset->custodian_name ?? $this->asset->user?->name;
        $lugar = $this->asset->field ?? $this->asset->location_zone;

        $mail = (new MailMessage)
            ->subject("Se te asignó mantenimiento: {$label}")
            ->greeting("Hola {$notifiable->name},")
            ->line("{$this->assignedBy->name} te ha asignado como responsable del mantenimiento del equipo **{$label}**.")
            ->line("**Próximo mantenimiento:** {$nextDate}".($interval ? " ({$interval})" : ''));

        if ($custodio) {
            $mail->line("**Custodio actual:** {$custodio}");
        }

        if ($lugar) {
            $mail->line("**Ubicación:** {$lugar}");
        }

        return $mail
            ->action('Ver activo', url("/soporte/assets/{$this->asset->id}/edit"))
            ->line('Helpdesk Confipetrol — Gestión IT');
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $label = $this->asset->asset_tag ?? $this->asset->hostname ?? "Activo #{$this->asset->id}";
        $nextDate = $this->asset->next_maintenance_at?->translatedFormat('d/m/Y') ?? 'Por definir';

        return FilamentNotification::make()
            ->title("Mantenimiento asignado: {$label}")
            ->body("Asignado por {$this->assignedBy->name} · Próximo: {$nextDate}")
            ->icon('heroicon-o-wrench-screwdriver')
            ->iconColor('info')
            ->actions([
                Action::make('view')
                    ->label('Ver activo')
                    ->url(url("/soporte/assets/{$this->asset->id}/edit"))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
