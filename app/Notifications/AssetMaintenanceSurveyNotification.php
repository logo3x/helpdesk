<?php

namespace App\Notifications;

use App\Models\Asset;
use App\Models\MaintenanceSurvey;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AssetMaintenanceSurveyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Asset $asset,
        public MaintenanceSurvey $survey,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $surveyUrl = url("/portal/maintenance-survey/{$this->survey->token}");
        $assetLabel = $this->asset->hostname ?? $this->asset->asset_tag ?? "Activo #{$this->asset->id}";

        return (new MailMessage)
            ->subject("Encuesta de mantenimiento — {$assetLabel}")
            ->greeting("Hola {$notifiable->name},")
            ->line("Se registró un mantenimiento en el equipo **{$assetLabel}**.")
            ->line('Por favor califica la atención recibida para ayudarnos a mejorar.')
            ->action('Responder encuesta', $surveyUrl)
            ->line('Si no respondes en 1 día, se registrará automáticamente la calificación máxima.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'maintenance_survey',
            'survey_id' => $this->survey->id,
            'asset_id' => $this->asset->id,
            'asset_label' => $this->asset->hostname ?? $this->asset->asset_tag ?? "Activo #{$this->asset->id}",
            'token' => $this->survey->token,
            'url' => "/portal/maintenance-survey/{$this->survey->token}",
        ];
    }
}
