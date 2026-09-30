<?php

namespace App\Filament\Resources\SlaConfigs\Tables;

use App\Enums\TicketPriority;
use App\Models\Department;
use App\Services\SlaService;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class SlaConfigsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultGroup(Group::make('department.name')->label('Departamento')->collapsible())
            ->columns([
                TextColumn::make('department.name')
                    ->label('Departamento')
                    ->searchable(),

                TextColumn::make('priority')
                    ->label('Prioridad')
                    ->badge()
                    ->sortable(),

                TextColumn::make('first_response_minutes')
                    ->label('Primera respuesta')
                    ->formatStateUsing(fn (int $state): string => SlaService::formatMinutes($state))
                    ->sortable(),

                TextColumn::make('resolution_minutes')
                    ->label('Resolución')
                    ->formatStateUsing(fn (int $state): string => SlaService::formatMinutes($state))
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->label('Activa'),

                TextColumn::make('updated_at')
                    ->label('Actualizada')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('department_id')
                    ->label('Departamento')
                    ->options(fn () => Department::orderBy('name')->pluck('name', 'id')),
                SelectFilter::make('priority')
                    ->label('Prioridad')
                    ->options(TicketPriority::class),
                TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->trueLabel('Solo activas')
                    ->falseLabel('Solo inactivas'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
