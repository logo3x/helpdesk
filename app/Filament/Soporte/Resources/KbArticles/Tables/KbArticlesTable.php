<?php

namespace App\Filament\Soporte\Resources\KbArticles\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class KbArticlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->title),

                TextColumn::make('department.name')
                    ->label('Departamento')
                    ->badge()
                    ->color('info')
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'draft' => 'Borrador',
                        'published' => 'Publicado',
                        'archived' => 'Archivado',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'draft' => 'warning',
                        'published' => 'success',
                        'archived' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('visibility')
                    ->label('Visibilidad')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'public' ? 'Pública' : 'Interna')
                    ->color(fn (string $state) => $state === 'public' ? 'success' : 'gray'),

                TextColumn::make('author.name')
                    ->label('Autor')
                    ->toggleable(),

                TextColumn::make('published_at')
                    ->label('Publicado el')
                    ->dateTime('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('department_id')
                    ->label('Departamento')
                    ->relationship('department', 'name'),

                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'draft' => 'Borrador',
                        'published' => 'Publicado',
                        'archived' => 'Archivado',
                    ]),

                TrashedFilter::make(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte'])),
                    ForceDeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte'])),
                    RestoreBulkAction::make()
                        ->visible(fn () => auth()->user()?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte'])),
                ]),
            ]);
    }
}
