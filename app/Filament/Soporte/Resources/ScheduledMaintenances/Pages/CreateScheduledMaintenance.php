<?php

namespace App\Filament\Soporte\Resources\ScheduledMaintenances\Pages;

use App\Enums\MaintenanceStatus;
use App\Filament\Soporte\Resources\ScheduledMaintenances\ScheduledMaintenanceResource;
use App\Models\ScheduledMaintenance;
use App\Models\User;
use App\Notifications\ScheduledMaintenanceAssignedNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateScheduledMaintenance extends CreateRecord
{
    protected static string $resource = ScheduledMaintenanceResource::class;

    /**
     * Se llena en handleRecordCreation cuando estamos en modo masivo,
     * con la lista de mantenimientos creados para la notificación
     * consolidada en afterCreate.
     *
     * @var array<int, ScheduledMaintenance>
     */
    protected array $bulkCreatedMaintenances = [];

    /**
     * Pre-selecciona el activo si viene ?asset_id=X en el URL (link
     * rápido desde la tabla de inventario). Fuerza modo individual.
     */
    public function mount(): void
    {
        parent::mount();

        if ($assetId = request()->query('asset_id')) {
            $this->form->fill([
                ...$this->form->getRawState(),
                'creation_mode' => 'individual',
                'asset_id' => (int) $assetId,
            ]);
        }
    }

    /**
     * En modo masivo el flow por defecto de Filament no sirve: hay que
     * crear N registros, uno por activo. Interceptamos handleRecordCreation.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $mode = $this->data['creation_mode'] ?? 'individual';

        if ($mode !== 'bulk') {
            $data['created_by_id'] = auth()->id();

            return static::getModel()::create($data);
        }

        $assetIds = $this->data['bulk_asset_ids'] ?? [];
        if (empty($assetIds)) {
            // Fallback: si no hay activos, no debería llegar aquí porque
            // el field es required. Pero mantenemos safety.
            abort(422, 'No se seleccionaron activos.');
        }

        $sharedFields = [
            'agent_id' => $data['agent_id'],
            'created_by_id' => auth()->id(),
            'scheduled_at' => $data['scheduled_at'],
            'status' => MaintenanceStatus::Pendiente->value,
            'frequency' => $data['frequency'],
            'progress_percent' => 0,
        ];

        $first = null;
        foreach ($assetIds as $assetId) {
            $maintenance = static::getModel()::create([
                ...$sharedFields,
                'asset_id' => (int) $assetId,
            ]);
            $this->bulkCreatedMaintenances[] = $maintenance;
            $first ??= $maintenance;
        }

        // Retorna el primero — Filament necesita un $record para el
        // redirect. Notif consolidada la mandamos en afterCreate.
        return $first;
    }

    /**
     * Notifica al agente. En modo individual, una notif con link al
     * mantenimiento. En modo masivo, una sola notif que dice "Se te
     * asignaron N mantenimientos" con link a la lista filtrada.
     */
    protected function afterCreate(): void
    {
        $mode = $this->data['creation_mode'] ?? 'individual';
        $agent = User::find($this->record->agent_id);
        if (! $agent) {
            return;
        }

        if ($mode === 'bulk' && count($this->bulkCreatedMaintenances) > 1) {
            $count = count($this->bulkCreatedMaintenances);

            FilamentNotification::make()
                ->title("Se te asignaron {$count} mantenimientos")
                ->body('Fecha: '.$this->record->scheduled_at->translatedFormat('d M Y').' · Frecuencia: '.$this->record->frequency->label())
                ->icon('heroicon-o-wrench-screwdriver')
                ->iconColor('info')
                ->sendToDatabase($agent);

            // Marca todos como notificados de asignación para no re-disparar
            // desde otros flujos.
            $ids = array_map(fn ($m) => $m->id, $this->bulkCreatedMaintenances);
            ScheduledMaintenance::whereIn('id', $ids)->update(['notified_agent_at' => now()]);

            // Flash de éxito en la pantalla del supervisor.
            FilamentNotification::make()
                ->title("{$count} mantenimientos creados")
                ->body("Se notificó a {$agent->name} con una sola alerta consolidada.")
                ->success()
                ->send();

            return;
        }

        // Individual (o bulk con 1 solo activo).
        $agent->notify(new ScheduledMaintenanceAssignedNotification($this->record));
        $this->record->forceFill(['notified_agent_at' => now()])->save();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
