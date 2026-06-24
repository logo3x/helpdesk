<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Historial completo del activo: mantenimientos, asignaciones, scans, etc.
 * Aparece en tiempo real en la página de edición del activo.
 * Permite crear entradas de mantenimiento manuales y editar/borrar notas.
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
                        'maintenance' => 'Mantenimiento',
                        'updated' => 'Actualización',
                        'assigned' => 'Asignación',
                        'scanned' => 'Scan automático',
                        'retired' => 'Retiro',
                        'created' => 'Creación',
                    ])
                    ->required()
                    ->native(false),

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
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();

                        return $data;
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
