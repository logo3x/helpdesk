<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Resources\Assets\AssetResource;
use App\Models\User;
use App\Services\InventoryImportService;
use App\Services\InventoryTemplateService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
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
                ->modalDescription('Sube el .xlsx con la estructura de la plantilla. Se crearán/actualizarán activos, proyectos, usuarios y departamentos según corresponda.')
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
                        ->helperText('Máximo 10 MB. Usa la plantilla para asegurar los encabezados correctos.'),
                    Checkbox::make('dry_run')
                        ->label('Previsualizar (dry-run) sin guardar cambios')
                        ->default(false),
                ])
                ->action(function (array $data): void {
                    $relative = (string) $data['file'];
                    $absolute = storage_path('app/'.$relative);

                    if (! is_file($absolute)) {
                        Notification::make()
                            ->title('No se pudo leer el archivo subido.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $report = app(InventoryImportService::class)
                        ->importFromFile($absolute, (bool) ($data['dry_run'] ?? false));

                    $errors = count($report['errors']);
                    $message = sprintf(
                        'Total: %d · Creadas: %d · Actualizadas: %d · Saltadas: %d · Errores: %d',
                        $report['total'],
                        $report['created'],
                        $report['updated'],
                        $report['skipped'],
                        $errors,
                    );

                    Notification::make()
                        ->title($data['dry_run'] ?? false ? 'Dry-run completado' : 'Import completado')
                        ->body($message)
                        ->{$errors === 0 ? 'success' : 'warning'}()
                        ->persistent()
                        ->send();

                    // El archivo subido se mantiene en storage/app/imports
                    // como respaldo auditoría — IT puede borrarlo después
                    // si lo desea.
                }),

            // Modal con el comando one-liner que IT pega en cada PC.
            // Reemplaza el "descargar .ps1 + crear tarea programada
            // manualmente" por una sola línea de PowerShell.
            Action::make('installInstructions')
                ->label('Cómo instalar el agente')
                ->icon('heroicon-o-command-line')
                ->color('info')
                ->modalWidth('3xl')
                ->modalHeading('Desplegar el agente en una PC')
                ->modalDescription('Pega esta línea en PowerShell (como administrador) en cada PC corporativa. Se descarga el agente, se crea una tarea programada semanal y se dispara un primer scan.')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->modalContent(fn () => view('filament.modals.install-agent-instructions', [
                    'installUrl' => route('agent.install'),
                    'uninstallUrl' => route('agent.uninstall'),
                ])),

            // Acceso directo al .ps1 raw (para auditoría manual antes
            // de aprobar el script en GPO).
            Action::make('downloadAgent')
                ->label('Ver script .ps1')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(asset('downloads/inventory-agent.ps1'))
                ->openUrlInNewTab(),

            // ── Generar token Sanctum del agente ──────────────────
            // Crea un token Bearer con la sola ability `inventory:scan`
            // que IT pega en el script PowerShell de cada PC. El
            // token plano se muestra UNA vez al crearlo (después
            // queda hasheado en BD).
            Action::make('generateAgentToken')
                ->label('Generar token del agente')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->modalHeading('Generar token del agente de inventario')
                ->modalDescription('Crea un token Bearer con permiso `inventory:scan` para usar en el script PowerShell. El token solo se mostrará una vez — copialo de inmediato.')
                ->schema([
                    Select::make('user_id')
                        ->label('Usuario dueño del token')
                        ->helperText('Recomendado: crear un usuario de servicio "agente-inventario" con rol técnico y emitir el token a su nombre.')
                        ->options(fn () => User::orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->required(),
                    TextInput::make('name')
                        ->label('Nombre del token')
                        ->placeholder('Ej: "Agente PC-RRHH-01" o "Tarea programada lunes"')
                        ->required()
                        ->maxLength(120),
                ])
                ->action(function (array $data): void {
                    /** @var User $user */
                    $user = User::findOrFail($data['user_id']);

                    // Solo la ability inventory:scan — un token con esta
                    // ability no puede hacer nada más en la app.
                    $token = $user->createToken($data['name'], ['inventory:scan']);

                    // El plainTextToken solo está disponible aquí; nunca
                    // se vuelve a poder leer (queda hasheado).
                    Notification::make()
                        ->title('Token generado')
                        ->body("Cópialo ahora — no se mostrará de nuevo:\n\n".$token->plainTextToken)
                        ->persistent()
                        ->success()
                        ->send();
                }),

            CreateAction::make(),
        ];
    }
}
