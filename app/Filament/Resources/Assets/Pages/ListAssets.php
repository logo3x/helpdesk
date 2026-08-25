<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Resources\Assets\AssetResource;
use App\Services\InventoryImportService;
use App\Services\InventoryTemplateService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListAssets extends ListRecords
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // ── Descargar plantilla Excel para carga masiva ──────────
            // Streamea el .xlsx generado por InventoryTemplateService
            // (incluye dropdowns, hoja de instrucciones y ejemplo).
            Action::make('downloadInventoryTemplate')
                ->label('📥 Plantilla Excel')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(function (): StreamedResponse {
                    $binary = app(InventoryTemplateService::class)->toBinary();

                    return response()->streamDownload(
                        fn () => print ($binary),
                        'plantilla-inventario.xlsx',
                        [
                            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ],
                    );
                }),

            // ── Carga masiva desde .xlsx ─────────────────────────────
            // Sube el archivo a storage/app/imports, corre el importer
            // (con --dry-run opcional) y muestra el reporte.
            Action::make('importInventory')
                ->label('📤 Importar inventario')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->modalHeading('Carga masiva de activos desde Excel')
                ->modalDescription('Sube el .xlsx con la estructura de la plantilla. El custodio se busca por cédula primero (Identificación) y por email después. Si no existe, se crea un stub según la regla del email.')
                ->modalSubmitActionLabel('Procesar')
                ->modalWidth('lg')
                ->schema([
                    FileUpload::make('file')
                        ->label('Archivo .xlsx')
                        ->required()
                        ->disk('local')
                        ->directory('imports')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ])
                        ->maxSize(10 * 1024)
                        ->helperText('Máximo 10 MB. Usá la plantilla para asegurar los encabezados correctos.'),
                    Checkbox::make('dry_run')
                        ->label('Previsualizar (dry-run) sin guardar cambios')
                        ->default(false),
                    Checkbox::make('strict_custodian')
                        ->label('Modo estricto: rechazar filas cuyo custodio no exista')
                        ->helperText('Recomendado si ya precargaste todas las personas. Evita que se creen stubs improvisados.')
                        ->default(false),
                ])
                ->action(function (array $data): void {
                    $relative = (string) $data['file'];
                    $disk = Storage::disk('local');

                    if (! $disk->exists($relative)) {
                        Notification::make()
                            ->title('No se pudo leer el archivo subido.')
                            ->body("Ruta esperada: {$relative}")
                            ->danger()
                            ->send();

                        return;
                    }

                    $absolute = $disk->path($relative);

                    $report = app(InventoryImportService::class)->importFromFile(
                        $absolute,
                        (bool) ($data['dry_run'] ?? false),
                        (bool) ($data['strict_custodian'] ?? false),
                    );

                    $errors = count($report['errors']);
                    $message = sprintf(
                        'Total: %d · Creados: %d · Actualizados: %d · Saltados: %d · Errores: %d · Usuarios stub: %d · Proyectos: %d · Deptos: %d',
                        $report['total'],
                        $report['created'],
                        $report['updated'],
                        $report['skipped'],
                        $errors,
                        $report['entities_created']['users'],
                        $report['entities_created']['projects'],
                        $report['entities_created']['departments'],
                    );

                    Notification::make()
                        ->title($data['dry_run'] ?? false ? 'Dry-run completado' : 'Import completado')
                        ->body($message)
                        ->{$errors === 0 ? 'success' : 'warning'}()
                        ->persistent()
                        ->send();
                }),

            CreateAction::make(),
        ];
    }
}
