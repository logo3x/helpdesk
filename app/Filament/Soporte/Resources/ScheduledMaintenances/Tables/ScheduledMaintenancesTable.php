<?php

namespace App\Filament\Soporte\Resources\ScheduledMaintenances\Tables;

use App\Enums\MaintenanceFrequency;
use App\Enums\MaintenanceStatus;
use App\Models\ScheduledMaintenance;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ScheduledMaintenancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('scheduled_at')
                    ->label('Fecha')
                    ->date('d M Y')
                    ->sortable()
                    ->description(fn (ScheduledMaintenance $r) => $r->isOverdue()
                        ? 'Vencido hace '.$r->scheduled_at->diffInDays(now()).' días'
                        : $r->scheduled_at->diffForHumans())
                    ->color(fn (ScheduledMaintenance $r) => $r->isOverdue() ? 'danger' : null),

                TextColumn::make('asset.asset_tag')
                    ->label('Activo (TAG)')
                    ->searchable()
                    ->sortable()
                    ->description(fn (ScheduledMaintenance $r) => $r->asset?->hostname),

                TextColumn::make('asset.type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn ($state) => strtoupper((string) $state))
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('agent.name')
                    ->label('Agente')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (MaintenanceStatus $state) => $state->label())
                    ->color(fn (MaintenanceStatus $state) => $state->color()),

                TextColumn::make('progress_percent')
                    ->label('Avance')
                    ->formatStateUsing(fn ($state) => $state.'%')
                    ->sortable(),

                TextColumn::make('frequency')
                    ->label('Frecuencia')
                    ->formatStateUsing(fn (?MaintenanceFrequency $state) => $state?->label() ?? '—')
                    ->toggleable(),

                TextColumn::make('completed_at')
                    ->label('Cerrado')
                    ->dateTime('d M Y H:i')
                    ->since()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Programado el')
                    ->dateTime('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        MaintenanceStatus::Pendiente->value => 'Pendiente',
                        MaintenanceStatus::Cumplido->value => 'Cumplido',
                        MaintenanceStatus::NoCumplido->value => 'No cumplido',
                    ]),

                SelectFilter::make('frequency')
                    ->label('Frecuencia')
                    ->options([
                        MaintenanceFrequency::Cuatrimestral->value => 'Cuatrimestral',
                        MaintenanceFrequency::Anual->value => 'Anual',
                    ]),

                SelectFilter::make('agent_id')
                    ->label('Agente')
                    ->relationship('agent', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('overdue')
                    ->label('Vencidos sin cumplir')
                    ->query(fn (Builder $q) => $q
                        ->where('status', MaintenanceStatus::Pendiente->value)
                        ->where('scheduled_at', '<', now()->startOfDay()))
                    ->toggle(),

                Filter::make('due_soon')
                    ->label('Próximos 30 días')
                    ->query(fn (Builder $q) => $q
                        ->where('status', MaintenanceStatus::Pendiente->value)
                        ->whereBetween('scheduled_at', [now()->startOfDay(), now()->addDays(30)]))
                    ->toggle(),
            ])
            ->defaultSort('scheduled_at', 'asc')
            ->recordActions([
                EditAction::make()
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning'),

                // Row action Delete individual. Solo visible para
                // supervisor+. Desvincula hijos (parent_id=NULL) antes
                // de borrar para no violar el FK constraint.
                DeleteAction::make()
                    ->label('Eliminar')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar mantenimiento programado')
                    ->modalDescription('¿Está segura/o de eliminar este mantenimiento?')
                    ->modalSubmitActionLabel('Eliminar')
                    ->visible(fn () => auth()->user()?->hasAnyRole([
                        'super_admin', 'admin', 'supervisor_soporte',
                    ]))
                    ->using(function (ScheduledMaintenance $record): void {
                        ScheduledMaintenance::where('parent_id', $record->id)
                            ->update(['parent_id' => null]);
                        $record->delete();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Bulk delete custom que:
                    //   1. Desvincula hijos (parent_id = NULL) para
                    //      no violar el FK constraint.
                    //   2. Borra (soft delete) los mtto seleccionados.
                    //   3. Muestra notif de éxito con el count.
                    //
                    // Alternativa a DeleteBulkAction::before() que
                    // en Filament 5 a veces no dispara el flow completo.
                    BulkAction::make('deleteSelected')
                        ->label('Borrar seleccionados')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Borrar Mantenimientos Programados seleccionados')
                        ->modalDescription('¿Está segura/o de hacer esto?')
                        ->modalSubmitActionLabel('Borrar')
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn () => auth()->user()?->hasAnyRole([
                            'super_admin', 'admin', 'supervisor_soporte',
                        ]))
                        // NOTA: usamos $records sin type-hint para que
                        // Filament pueda inyectar Collection|EloquentCollection|
                        // LazyCollection según venga. Con type-hint estricto
                        // en v5, a veces llega vacío por incompatibilidad
                        // de resolución del container.
                        ->action(function ($records): void {
                            // Normaliza a array de IDs sin importar el
                            // tipo concreto de collection que llegue.
                            $ids = collect($records)
                                ->map(fn ($r) => is_object($r) ? $r->id : $r)
                                ->filter()
                                ->all();

                            if ($ids === []) {
                                Notification::make()
                                    ->title('No se seleccionaron mantenimientos')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            // Desvincula hijos.
                            ScheduledMaintenance::whereIn('parent_id', $ids)
                                ->update(['parent_id' => null]);

                            // Borra (soft delete) los seleccionados.
                            $count = ScheduledMaintenance::whereIn('id', $ids)->delete();

                            Notification::make()
                                ->title("Se borraron {$count} mantenimiento(s)")
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }
}
