<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Services\PeopleImportService;
use App\Services\PeopleTemplateService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        $canBulk = auth()->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;

        return array_filter([
            // ── Descargar plantilla Excel para precarga de personas ─
            $canBulk
                ? Action::make('downloadPeopleTemplate')
                    ->label('📥 Plantilla personas')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->tooltip('Descargar la plantilla oficial .xlsx para precargar usuarios en lote.')
                    ->action(function (): StreamedResponse {
                        $binary = app(PeopleTemplateService::class)->toBinary();

                        return response()->streamDownload(
                            fn () => print ($binary),
                            'plantilla-personas.xlsx',
                            [
                                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            ],
                        );
                    })
                : null,

            // ── Carga masiva de personas desde .xlsx ────────────────
            $canBulk
                ? Action::make('importPeople')
                    ->label('📤 Precargar personas')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->modalHeading('Precarga masiva de personas')
                    ->modalDescription('Subí el .xlsx con la lista de empleados. Los que tengan email @confipetrol.com quedan como "Azure pendiente" y se enlazan solos al primer login SSO. El resto queda como cuenta local con password = primeros 8 dígitos de la cédula.')
                    ->modalSubmitActionLabel('Procesar')
                    ->modalWidth('lg')
                    ->schema([
                        FileUpload::make('file')
                            ->label('Archivo .xlsx')
                            ->required()
                            ->disk('local')
                            ->directory('imports/people')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                            ])
                            ->maxSize(10 * 1024)
                            ->helperText('Máximo 10 MB. Usá la plantilla para garantizar los encabezados correctos.'),
                        Checkbox::make('dry_run')
                            ->label('Previsualizar (dry-run) sin guardar cambios')
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

                        $report = app(PeopleImportService::class)
                            ->importFromFile($absolute, (bool) ($data['dry_run'] ?? false));

                        $errors = count($report['errors']);
                        $message = sprintf(
                            'Total: %d · Azure creadas: %d · Locales creadas: %d · Actualizadas: %d · Saltadas: %d · Errores: %d',
                            $report['total'],
                            $report['created_azure'],
                            $report['created_local'],
                            $report['updated'],
                            $report['skipped'],
                            $errors,
                        );

                        Notification::make()
                            ->title($data['dry_run'] ?? false ? 'Dry-run completado' : 'Precarga completada')
                            ->body($message)
                            ->{$errors === 0 ? 'success' : 'warning'}()
                            ->persistent()
                            ->send();
                    })
                : null,

            CreateAction::make(),
        ]);
    }
}
