<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Resources\Assets\AssetResource;
use App\Models\AssetHandover;
use App\Models\User;
use App\Services\AssetHandoverService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EditAsset extends EditRecord
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // ── Ver hoja de vida (timeline del activo) ──────────────
            Action::make('viewLifecycle')
                ->label('Hoja de vida')
                ->icon('heroicon-o-clock')
                ->color('info')
                ->tooltip('Ver historial completo: scans, actas, mantenimientos y cambios')
                ->url(fn () => route('assets.lifecycle.pdf', ['asset' => $this->record]))
                ->openUrlInNewTab(),

            // ── Generar acta de entrega (formato IT-ADM1-F-5 v3) ──
            // Crea un AssetHandover con snapshot, genera el PDF y lo
            // descarga inmediatamente. Si el receptor es distinto al
            // user_id actual del activo, actualiza la asignación.
            Action::make('generateHandover')
                ->label('Generar acta de entrega')
                ->icon('heroicon-o-document-text')
                ->color('warning')
                ->tooltip('Genera PDF oficial IT-ADM1-F-5 v3 y actualiza el custodio')
                ->modalHeading('Generar acta de entrega')
                ->modalDescription('Genera el PDF oficial IT-ADM1-F-5 v3 con los datos actuales del equipo. Si eliges un receptor distinto al custodio actual, también se actualiza la asignación del activo.')
                ->modalSubmitActionLabel('Generar y descargar')
                ->modalWidth('xl')
                ->fillForm(fn () => [
                    'received_by_user_id' => $this->record->user_id,
                    'condition_at_delivery' => 'bueno',
                    'reference' => 'Entrega de '.strtoupper((string) $this->record->type),
                ])
                ->schema([
                    Select::make('received_by_user_id')
                        ->label('Custodio (recibe)')
                        ->relationship('user', 'name')
                        ->searchable(['name', 'email', 'identification'])
                        ->preload()
                        ->required()
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->custodianLabel())
                        ->helperText('La persona que firma el acta y se vuelve responsable del equipo.'),

                    Select::make('condition_at_delivery')
                        ->label('Condición de entrega')
                        ->options([
                            'bueno' => 'Bueno',
                            'regular' => 'Regular',
                            'reacondicionado' => 'Reacondicionado',
                        ])
                        ->default('bueno')
                        ->required()
                        ->native(false),

                    TextInput::make('reference')
                        ->label('Referencia')
                        ->placeholder('Ej: Entrega de LAPTOP')
                        ->maxLength(255),

                    Textarea::make('observations')
                        ->label('Observaciones')
                        ->placeholder('Ej: "Acta #: 1432 --- CON CARGADOR"')
                        ->rows(2)
                        ->maxLength(500),
                ])
                ->action(function (array $data) {
                    $receivedBy = User::findOrFail($data['received_by_user_id']);

                    $handover = app(AssetHandoverService::class)->generate(
                        asset: $this->record,
                        receivedBy: $receivedBy,
                        extra: [
                            'condition_at_delivery' => $data['condition_at_delivery'],
                            'reference' => $data['reference'] ?? null,
                            'observations' => $data['observations'] ?? null,
                        ],
                    );

                    Notification::make()
                        ->title("Acta #{$handover->acta_number} generada")
                        ->body('Se descargará automáticamente el PDF.')
                        ->success()
                        ->send();

                    // Devolver el PDF como descarga directa.
                    return $this->downloadHandover($handover);
                }),

            // ── Cargar acta firmada en físico ────────────────────
            Action::make('uploadSignedHandover')
                ->label('Cargar acta firmada')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->tooltip('Sube el PDF o imagen del acta física firmada por el custodio')
                ->modalHeading('Cargar acta de entrega firmada')
                ->modalDescription('Selecciona el acta del handover y sube el PDF o imagen escaneada firmada.')
                ->modalWidth('lg')
                ->visible(fn () => $this->record->handovers()->exists())
                ->schema([
                    Select::make('handover_id')
                        ->label('Acta de entrega')
                        ->options(fn () => $this->record->handovers()
                            ->get()
                            ->mapWithKeys(fn ($h) => [
                                $h->id => "Acta #{$h->acta_number} — ".($h->delivered_at?->format('d/m/Y') ?? ''),
                            ]))
                        ->required()
                        ->native(false),

                    FileUpload::make('signed_file')
                        ->label('Archivo firmado (PDF o imagen)')
                        ->disk('local')
                        ->directory('handovers/signed')
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                        ->maxSize(10240)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $handover = AssetHandover::findOrFail($data['handover_id']);

                    $handover->forceFill([
                        'uploaded_signed_pdf_path' => $data['signed_file'],
                        'uploaded_signed_at' => now(),
                        'uploaded_by_user_id' => auth()->id(),
                    ])->save();

                    Notification::make()
                        ->title('Acta cargada correctamente')
                        ->body("El archivo fue asociado al acta #{$handover->acta_number}.")
                        ->success()
                        ->send();
                }),

            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * Streamea el PDF de un acta para descarga inmediata desde la action.
     */
    protected function downloadHandover(AssetHandover $handover): StreamedResponse
    {
        $filename = sprintf(
            'acta_%d_%s.pdf',
            $handover->acta_number,
            preg_replace('/[^A-Za-z0-9_-]/', '_', strtoupper((string) ($handover->receivedBy?->name ?? 'equipo'))),
        );

        return Storage::disk('local')->download($handover->pdf_path, $filename);
    }
}
