<?php

namespace App\Filament\Resources\SatisfactionSurveys\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SatisfactionSurveysTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('ticket.number')
                    ->label('Ticket')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('ticket.subject')
                    ->label('Asunto')
                    ->limit(50),

                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->searchable(),

                TextColumn::make('ticket.department.name')
                    ->label('Departamento')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('rating')
                    ->label('Promedio')
                    ->sortable(),

                TextColumn::make('responded_at')
                    ->label('Respondida')
                    ->since()
                    ->sortable()
                    ->placeholder('Pendiente'),

                TextColumn::make('created_at')
                    ->label('Enviada')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
