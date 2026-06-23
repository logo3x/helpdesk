<?php

namespace App\Notifications;

use App\Models\AssetHandover;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AssetHandoverConfirmedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly AssetHandover $handover) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $handover = $this->handover;
        $asset = $handover->asset;
        $confirmedAt = $handover->received_confirmed_at->translatedFormat('d/m/Y H:i');

        return (new MailMessage)
            ->subject("Acta #{$handover->acta_number} confirmada — Helpdesk Confipetrol")
            ->greeting("Hola, {$notifiable->name}")
            ->line('El custodio ha confirmado la recepción del activo asignado a su cargo.')
            ->line("**Acta de entrega:** #{$handover->acta_number}")
            ->line("**Activo:** {$asset->asset_tag} — {$asset->manufacturer} {$asset->model}")
            ->line("**Número de serie:** {$asset->serial_number}")
            ->line("**Confirmado el:** {$confirmedAt}")
            ->line('Los datos del activo han sido validados. Puedes generar la hoja de vida del equipo desde el panel de soporte.')
            ->salutation('Helpdesk Confipetrol');
    }
}
