<?php

namespace App\Filament\Soporte\Resources\Assets\Pages;

use App\Filament\Soporte\Resources\Assets\AssetResource;
use App\Models\User;
use App\Services\AssetHandoverService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditAsset extends EditRecord
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewLifecycle')
                ->label('📋 Hoja de vida')
                ->icon('heroicon-o-clock')
                ->color('info')
                ->url(fn () => AssetResource::getUrl('lifecycle', ['record' => $this->record])),

            Action::make('generateHandover')
                ->label('📄 Generar acta de entrega')
                ->icon('heroicon-o-document-text')
                ->color('warning')
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
                        ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->name} · {$record->email}"),

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

                    return Storage::disk('local')->download(
                        $handover->pdf_path,
                        sprintf('acta_%d_%s.pdf',
                            $handover->acta_number,
                            preg_replace('/[^A-Za-z0-9_-]/', '_', strtoupper((string) ($handover->receivedBy?->name ?? 'equipo'))),
                        ),
                    );
                }),

            DeleteAction::make()->visible(fn () => auth()->user()?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte'])),
        ];
    }
}
