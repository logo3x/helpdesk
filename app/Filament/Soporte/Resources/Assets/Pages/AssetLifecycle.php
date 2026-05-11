<?php

namespace App\Filament\Soporte\Resources\Assets\Pages;

use App\Filament\Resources\Assets\Pages\AssetLifecycle as AdminAssetLifecycle;
use App\Filament\Soporte\Resources\Assets\AssetResource;
use Filament\Actions\Action;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Hoja de vida del activo dentro del panel /soporte.
 *
 * Hereda toda la lógica del page del panel admin para no duplicar
 * código; solo reemplaza el resource para que la URL y el breadcrumb
 * se generen contra /soporte.
 */
class AssetLifecycle extends AdminAssetLifecycle
{
    protected static string $resource = AssetResource::class;

    public function mount(int|string $record): void
    {
        $this->record = AssetResource::resolveRecordRouteBinding($record)
            ?? throw new NotFoundHttpException;

        $this->record->loadMissing([
            'user',
            'department',
            'project',
            'maintenanceResponsible',
            'handovers.receivedBy',
            'handovers.deliveredBy',
            'histories.user',
            'scans',
            'software',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToEdit')
                ->label('← Volver al activo')
                ->color('gray')
                ->url(AssetResource::getUrl('edit', ['record' => $this->record])),
        ];
    }
}
