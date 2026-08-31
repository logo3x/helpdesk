<?php

namespace App\Notifications;

use App\Models\SlaConfig;
use Filament\Notifications\Actions\Action as FilamentAction;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Se envía a todos los usuarios cuando cambian los umbrales de un
 * SlaConfig. Aparece en la campanita con link al listado de tickets.
 */
class SlaConfigChangedNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<int, string>  $changedFields
     */
    public function __construct(
        public SlaConfig $slaConfig,
        public array $changedFields,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $priority = $this->slaConfig->priority?->value ?? 'general';
        $dept = $this->slaConfig->department?->name ?? 'todos los departamentos';
        $isCreated = in_array('created', $this->changedFields, true);

        $title = $isCreated
            ? "Nuevo SLA para prioridad {$priority}"
            : "SLA de prioridad {$priority} actualizado";

        $body = $isCreated
            ? "Se creó una nueva configuración SLA para {$dept}."
            : "Los tiempos de respuesta/resolución del SLA de {$dept} cambiaron. Revisá tus tickets abiertos.";

        return FilamentNotification::make()
            ->title($title)
            ->body($body)
            ->icon('heroicon-o-clock')
            ->iconColor('warning')
            ->actions([
                FilamentAction::make('view_tickets')
                    ->label('Ver tickets abiertos')
                    ->url(url('/soporte/tickets')),
            ])
            ->getDatabaseMessage();
    }
}
