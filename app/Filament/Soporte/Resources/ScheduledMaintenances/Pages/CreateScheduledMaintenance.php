<?php

namespace App\Filament\Soporte\Resources\ScheduledMaintenances\Pages;

use App\Filament\Soporte\Resources\ScheduledMaintenances\ScheduledMaintenanceResource;
use App\Models\User;
use App\Notifications\ScheduledMaintenanceAssignedNotification;
use Filament\Resources\Pages\CreateRecord;

class CreateScheduledMaintenance extends CreateRecord
{
    protected static string $resource = ScheduledMaintenanceResource::class;

    /**
     * Pre-selecciona el activo si viene ?asset_id=X en el URL (link
     * rápido desde la tabla de inventario).
     */
    public function mount(): void
    {
        parent::mount();

        if ($assetId = request()->query('asset_id')) {
            $this->form->fill([
                ...$this->form->getRawState(),
                'asset_id' => (int) $assetId,
            ]);
        }
    }

    /**
     * Fuerza el created_by_id del usuario autenticado — nunca del payload.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_id'] = auth()->id();

        return $data;
    }

    /**
     * Notifica al agente asignado por campanita de Filament.
     */
    protected function afterCreate(): void
    {
        $agent = User::find($this->record->agent_id);
        if (! $agent) {
            return;
        }

        $agent->notify(new ScheduledMaintenanceAssignedNotification($this->record));

        $this->record->forceFill(['notified_agent_at' => now()])->save();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
