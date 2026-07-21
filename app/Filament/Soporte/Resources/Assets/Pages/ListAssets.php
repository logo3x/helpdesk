<?php

namespace App\Filament\Soporte\Resources\Assets\Pages;

use App\Filament\Soporte\Resources\Assets\AssetResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAssets extends ListRecords
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadScanner')
                ->label('Descargar ScanConfi')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->tooltip('Descarga el script PowerShell personalizado con tu usuario para escanear PCs de clientes')
                ->url('/api/inventory/scanner/download')
                ->openUrlInNewTab(false),

            CreateAction::make(),
        ];
    }
}
