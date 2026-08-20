<?php

namespace App\Filament\Soporte\Resources\ScheduledMaintenances\Tables;

use App\Enums\MaintenanceFrequency;
use App\Enums\MaintenanceStatus;
use App\Models\ScheduledMaintenance;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
