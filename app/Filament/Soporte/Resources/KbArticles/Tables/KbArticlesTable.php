<?php

namespace App\Filament\Soporte\Resources\KbArticles\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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

                IconColumn::make('pending_review_at')
                    ->label('Por revisar')
                    ->boolean()
                    ->trueIcon('heroicon-o-paper-airplane')
                    ->trueColor('info')
                    ->falseIcon('heroicon-o-minus')
                    ->falseColor('gray')
                    ->getStateUsing(fn ($record) => $record->pending_review_at !== null)
                    ->tooltip(fn ($record) => $record->pending_review_at
                        ? 'Pendiente de aprobación desde '.$record->pending_review_at->diffForHumans()
                        : null),

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
                Filter::make('pending_review')
                    ->label('Pendientes de revisión')
                    ->query(fn (Builder $query) => $query->whereNotNull('pending_review_at')->where('status', 'draft'))
                    ->toggle(),

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
