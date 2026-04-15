<?php

namespace App\Filament\Resources\ChatSessions\Tables;

use App\Filament\Resources\ChatSessions\Pages\ViewChatSession;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ChatSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'info',
                        'escalated' => 'warning',
                        'closed' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('messages_count')
                    ->label('Mensajes')
                    ->counts('messages')
                    ->sortable(),
                TextColumn::make('channel')
                    ->label('Canal')
                    ->badge(),
                TextColumn::make('escalatedTicket.number')
                    ->label('Ticket escalado')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Inicio')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Última actividad')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'active' => 'Activa',
                        'escalated' => 'Escalada',
                        'closed' => 'Cerrada',
                    ]),
                SelectFilter::make('channel')
                    ->label('Canal')
                    ->options([
                        'web' => 'Web',
                        'teams' => 'Teams',
                    ]),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('Ver conversación')
                    ->icon('heroicon-m-eye')
                    ->url(fn ($record) => ViewChatSession::getUrl(['record' => $record])),
            ])
            ->toolbarActions([]);
    }
}
