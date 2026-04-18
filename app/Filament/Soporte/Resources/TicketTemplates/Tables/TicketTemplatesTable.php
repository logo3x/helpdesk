<?php

namespace App\Filament\Soporte\Resources\TicketTemplates\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Contracts\HasLabel;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TicketTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('subject')
                    ->label('Asunto')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->subject),

                TextColumn::make('category.department.name')
                    ->label('Departamento')
                    ->badge()
                    ->color('info')
                    ->placeholder('—'),

                TextColumn::make('category.name')
                    ->label('Categoría')
                    ->placeholder('—'),

                TextColumn::make('impact')
                    ->label('Impacto')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof HasLabel ? $state->getLabel() : ($state ?: '—'))
                    ->placeholder('—'),

                TextColumn::make('urgency')
                    ->label('Urgencia')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof HasLabel ? $state->getLabel() : ($state ?: '—'))
                    ->placeholder('—'),

                IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),

                TextColumn::make('sort_order')
                    ->label('Orden')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category.department_id')
                    ->label('Departamento')
                    ->relationship('category.department', 'name'),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte'])),
                ]),
            ]);
    }
}
