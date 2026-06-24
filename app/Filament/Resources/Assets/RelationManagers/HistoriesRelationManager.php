<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use App\Models\Asset;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Historial completo del activo: mantenimientos, asignaciones, scans, etc.
 *
 * - Se refresca automáticamente cada 15 segundos (poll).
 * - Los cambios en el formulario del activo (custodio, estado, etc.)
 *   se registran automáticamente via Asset::booted() TRACKED_FIELDS.
 * - El modal "Registrar evento" permite crear entradas manuales;
 *   cuando el tipo es "Mantenimiento" también actualiza los campos
 *   del activo (last_maintenance_at, interval, responsable).
 */
class HistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'histories';

    protected static ?string $title = 'Historial del activo';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('action')
                    ->label('Tipo de evento')
                    ->options([
                        'maintenance' => '🔧 Mantenimiento',
                        'updated' => '✏️ Actualización',
                        'assigned' => '👤 Asignación',
                        'scanned' => '🖥️ Scan automático',
                        'retired' => '📦 Retiro',
                        'created' => '✅ Creación',
                    ])
                    ->required()
                    ->native(false)
                    ->live(),

                // Campos extra que solo aparecen cuando el evento es "Mantenimiento"
                DatePicker::make('maintenance_done_at')
                    ->label('Fecha del mantenimiento')
                    ->displayFormat('d/m/Y')
                    ->native(false)
                    ->default(now())
                    ->visible(fn ($get) => $get('action') === 'maintenance')
                    ->required(fn ($get) => $get('action') === 'maintenance'),

                TextInput::make('maintenance_interval_days')
                    ->label('Frecuencia (días)')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(3650)
                    ->placeholder('120')
                    ->helperText('120 = trimestral · 180 = semestral · 365 = anual')
                    ->visible(fn ($get) => $get('action') === 'maintenance'),

                Select::make('maintenance_responsible_id')
                    ->label('Responsable')
                    ->relationship(
                        'maintenanceResponsible',
                        'name',
                        fn ($query) => $query->whereHas('roles', fn ($q) => $q->whereIn('name', [
                            'super_admin', 'admin', 'supervisor_soporte', 'agente_soporte', 'tecnico_campo',
                        ])),
                    )
                    ->searchable(['name', 'email'])
                    ->preload()
                    ->placeholder('Sin asignar')
                    ->visible(fn ($get) => $get('action') === 'maintenance'),

                Textarea::make('notes')
                    ->label('Observaciones')
                    ->rows(3)
                    ->placeholder('Ej: Limpieza interna + cambio de pasta térmica')
                    ->maxLength(1000)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('action')
            ->defaultSort('created_at', 'desc')
            ->poll('15s')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->width('130px'),

                TextColumn::make('action')
                    ->label('Evento')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'maintenance' => 'warning',
                        'assigned' => 'info',
                        'scanned' => 'gray',
                        'retired' => 'danger',
                        'created' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'maintenance' => 'Mantenimiento',
                        'assigned' => 'Asignación',
                        'scanned' => 'Scan',
                        'retired' => 'Retiro',
                        'created' => 'Creación',
                        'updated' => 'Actualización',
                        default => ucfirst($state),
                    })
                    ->width('140px'),

                TextColumn::make('user.name')
                    ->label('Registrado por')
                    ->placeholder('Sistema')
                    ->width('160px'),

                TextColumn::make('field')
                    ->label('Campo')
                    ->placeholder('—')
                    ->width('140px'),

                TextColumn::make('old_value')
                    ->label('Antes')
                    ->placeholder('—')
                    ->limit(40)
                    ->width('140px'),

                TextColumn::make('new_value')
                    ->label('Después')
                    ->placeholder('—')
                    ->limit(40)
                    ->width('140px'),

                TextColumn::make('notes')
                    ->label('Observaciones')
                    ->placeholder('—')
                    ->limit(80)
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label('Tipo')
                    ->options([
                        'maintenance' => 'Mantenimiento',
                        'assigned' => 'Asignación',
                        'scanned' => 'Scan',
                        'updated' => 'Actualización',
                        'retired' => 'Retiro',
                        'created' => 'Creación',
                    ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Registrar evento')
                    ->modalHeading('Registrar evento en el historial')
                    ->mutateDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();

                        return $data;
                    })
                    ->after(function (array $data): void {
                        // Si es mantenimiento, actualizar los campos del activo
                        if (($data['action'] ?? '') !== 'maintenance') {
                            return;
                        }

                        /** @var Asset $asset */
                        $asset = $this->getOwnerRecord();

                        $fields = array_filter([
                            'last_maintenance_at' => $data['maintenance_done_at'] ?? null,
                            'maintenance_interval_days' => $data['maintenance_interval_days'] ?? null,
                            'maintenance_responsible_id' => $data['maintenance_responsible_id'] ?? null,
                        ], fn ($v) => $v !== null && $v !== '');

                        if (! empty($fields)) {
                            // Skip auto-history: ya se creó el registro manual en CreateAction
                            $asset->skipAutoHistory = true;
                            $asset->forceFill($fields)->save();
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalHeading('Editar entrada del historial'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
