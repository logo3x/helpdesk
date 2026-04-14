<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Department;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('department.name')
                    ->label('Departamento')
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tickets_count')
                    ->counts('tickets')
                    ->label('Tickets')
                    ->badge()
                    ->color('info'),
                IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label('Orden')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('department_id')
                    ->label('Departamento')
                    ->options(fn () => Department::where('is_active', true)->pluck('name', 'id')->all())
                    ->searchable(),
                TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->trueLabel('Solo activas')
                    ->falseLabel('Solo inactivas'),
            ])
            ->defaultSort('department_id')
            ->defaultGroup('department.name')
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
