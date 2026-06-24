<?php

namespace App\Notifications;

use App\Models\Asset;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Notificación de mantenimiento próximo.
 *
 * - campanita (database): un activo específico vence en 7 días.
 * - correo (mail): resumen semanal con todos los activos que vencen
 *   en los próximos 14 días. Se construye con una Collection de assets.
 */
class AssetMaintenanceDueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, Asset>|null  $assetsForMail  Solo para el canal mail (resumen semanal).
     */
    public function __construct(
        public readonly ?Asset $asset = null,
        public readonly ?Collection $assetsForMail = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        // Si viene una colección, es el correo semanal.
        // Si viene un asset individual, es la campanita + mail de aviso inmediato.
        return $this->assetsForMail !== null ? ['mail'] : ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // ── Resumen semanal (colección de activos) ─────────────────────────
        if ($this->assetsForMail !== null) {
            $msg = (new MailMessage)
                ->subject('Mantenimientos de equipos próximos a vencer — Helpdesk Confipetrol')
                ->greeting("Hola {$notifiable->name},")
                ->line('Los siguientes equipos tienen mantenimiento programado para las **próximas 2 semanas**. Por favor planifica las visitas.')
                ->line('');

            foreach ($this->assetsForMail as $a) {
                $label = $a->asset_tag ?? $a->hostname ?? "Activo #{$a->id}";
                $date = $a->next_maintenance_at?->translatedFormat('d/m/Y') ?? '—';
                $custodio = $a->custodian_name ?? $a->user?->name ?? 'Sin custodio';
                $campo = $a->field ? " · {$a->field}" : '';
                $msg->line("🔧 **{$label}** — Vence: {$date} · Custodio: {$custodio}{$campo}");
            }

            return $msg
                ->action('Ver inventario', url('/soporte/assets'))
                ->line('Este correo se envía automáticamente cada semana.');
        }

        // ── Aviso individual (una semana antes) ────────────────────────────
        $label = $this->asset?->asset_tag ?? $this->asset?->hostname ?? "Activo #{$this->asset?->id}";
        $date = $this->asset?->next_maintenance_at?->translatedFormat('d/m/Y') ?? '—';

        return (new MailMessage)
            ->subject("Mantenimiento próximo: {$label} vence el {$date}")
            ->greeting("Hola {$notifiable->name},")
            ->line("El equipo **{$label}** tiene mantenimiento programado para el **{$date}**.")
            ->line('Por favor coordina con el custodio para realizar la visita a tiempo.')
            ->action('Ver activo', url("/soporte/assets/{$this->asset?->id}/edit"))
            ->line('Helpdesk Confipetrol — Gestión IT');
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $label = $this->asset?->asset_tag ?? $this->asset?->hostname ?? "Activo #{$this->asset?->id}";
        $date = $this->asset?->next_maintenance_at?->translatedFormat('d/m/Y') ?? '—';

        return FilamentNotification::make()
            ->title("Mantenimiento próximo: {$label}")
            ->body("Vence el {$date}. Coordina la visita a tiempo.")
            ->icon('heroicon-o-wrench-screwdriver')
            ->iconColor('warning')
            ->actions([
                Action::make('view')
                    ->label('Ver activo')
                    ->url(url("/soporte/assets/{$this->asset?->id}/edit"))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
