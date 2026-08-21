<?php

namespace App\Filament\Soporte\Resources\ScheduledMaintenances\Pages;

use App\Filament\Soporte\Resources\ScheduledMaintenances\ScheduledMaintenanceResource;
use App\Models\ScheduledMaintenance;
use App\Models\User;
use App\Notifications\ScheduledMaintenanceAssignedNotification;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditScheduledMaintenance extends EditRecord
{
    protected static string $resource = ScheduledMaintenanceResource::class;

    /**
     * Si el supervisor cambia el agente asignado, se notifica al nuevo.
     * Guardamos el original antes del save para comparar.
     */
    protected ?int $originalAgentId = null;

    protected function beforeSave(): void
    {
        $this->originalAgentId = $this->record->getOriginal('agent_id');
    }

    protected function afterSave(): void
    {
        $newAgentId = $this->record->agent_id;
        if ($newAgentId && $this->originalAgentId !== $newAgentId) {
            $agent = User::find($newAgentId);
            $agent?->notify(new ScheduledMaintenanceAssignedNotification($this->record));

            $this->record->forceFill(['notified_agent_at' => now()])->save();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => auth()->user()?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte']))
                ->using(function ($record): void {
                    // Desvincular hijos primero para no violar el FK.
                    ScheduledMaintenance::where('parent_id', $record->id)
                        ->update(['parent_id' => null]);
                    $record->delete();
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
