<?php

namespace App\Filament\Soporte\Resources\ScheduledMaintenances\Tables;

use App\Enums\MaintenanceFrequency;
use App\Enums\MaintenanceStatus;
use App\Models\ScheduledMaintenance;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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

                TextColumn::make('asset.location_zone')
                    ->label('Zona')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('asset.field')
                    ->label('Campo')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('asset.management_area')
                    ->label('Gerencia')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

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
                    ->color('warning')
                    ->button(),

                // Row action Delete individual como Action custom que
                // NO se llama "delete" (así Shield no lo intercepta con
                // su policy auto-generado). Solo visible para supervisor+.
                // Desvincula hijos antes del delete para no violar el FK.
                Action::make('deleteRow')
                    ->label('Eliminar')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->button()
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar mantenimiento programado')
                    ->modalDescription('¿Está segura/o de eliminar este mantenimiento?')
                    ->modalSubmitActionLabel('Eliminar')
                    ->modalIcon('heroicon-o-trash')
                    ->modalIconColor('danger')
                    ->visible(fn () => auth()->user()?->hasAnyRole([
                        'super_admin', 'admin', 'supervisor_soporte',
                    ]) ?? false)
                    // Resolvemos $record de varias formas y hacemos
                    // HARD delete via DB directo, evitando cualquier
                    // cache de Eloquent, softdelete o listener rar_o.
                    ->action(function ($record, Action $action, $arguments) {
                        // 1. Intenta el argumento estándar.
                        $id = null;
                        if (is_object($record) && isset($record->id)) {
                            $id = $record->id;
                        } elseif (is_numeric($record)) {
                            $id = (int) $record;
                        }

                        // 2. Fallback: $action->getRecord().
                        if (! $id) {
                            $r = $action->getRecord();
                            if (is_object($r) && isset($r->id)) {
                                $id = $r->id;
                            }
                        }

                        // 3. Fallback: $arguments['record'] (Filament v5).
                        if (! $id && is_array($arguments)) {
                            $id = $arguments['record'] ?? null;
                        }

                        if (! $id) {
                            Notification::make()
                                ->title('No se pudo resolver el registro a eliminar')
                                ->danger()
                                ->send();

                            return;
                        }

                        // HARD delete via query builder — evita observers,
                        // softdelete, cache y cualquier interferencia.
                        // Primero desvinculo hijos.
                        DB::table('scheduled_maintenances')
                            ->where('parent_id', $id)
                            ->update(['parent_id' => null]);

                        $deleted = DB::table('scheduled_maintenances')
                            ->where('id', $id)
                            ->delete();

                        if ($deleted === 0) {
                            Notification::make()
                                ->title('No se encontró el registro')
                                ->body("ID #{$id} no existe en la BD.")
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Mantenimiento eliminado')
                            ->body("Registro #{$id} borrado permanentemente.")
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Bulk delete custom con HARD delete via DB directo,
                    // evitando problemas de resolución de records de
                    // Filament v5.5 y de softdelete no aplicado.
                    BulkAction::make('deleteSelected')
                        ->label('Eliminar seleccionados')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar mantenimientos programados')
                        ->modalDescription('Los mantenimientos seleccionados se eliminarán permanentemente.')
                        ->modalSubmitActionLabel('Eliminar')
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn () => auth()->user()?->hasAnyRole([
                            'super_admin', 'admin', 'supervisor_soporte',
                        ]) ?? false)
                        ->action(function ($records) {
                            $ids = collect($records)
                                ->map(fn ($r) => is_object($r) ? $r->id : (int) $r)
                                ->filter()
                                ->all();

                            if ($ids === []) {
                                Notification::make()
                                    ->title('No se seleccionaron registros')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            // Desvincula hijos.
                            DB::table('scheduled_maintenances')
                                ->whereIn('parent_id', $ids)
                                ->update(['parent_id' => null]);

                            // HARD delete.
                            $count = DB::table('scheduled_maintenances')
                                ->whereIn('id', $ids)
                                ->delete();

                            Notification::make()
                                ->title("Se eliminaron {$count} mantenimiento(s)")
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }
}
