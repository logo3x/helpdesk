<?php

namespace App\Filament\Soporte\Resources\Tickets\RelationManagers;

use App\Models\Ticket;
use App\Services\TicketService;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $title = 'Comentarios';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('body')
                    ->label('Comentario')
                    ->required()
                    ->rows(4)
                    ->maxLength(5000)
                    ->columnSpanFull(),

                Toggle::make('is_private')
                    ->label('Comentario interno (no visible al solicitante)')
                    ->default(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('body')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Autor')
                    ->badge()
                    ->color('primary'),
                TextColumn::make('body')
                    ->label('Comentario')
                    ->wrap()
                    ->limit(140),
                IconColumn::make('is_private')
                    ->label('Interno')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-eye')
                    ->trueColor('warning')
                    ->falseColor('success'),
                TextColumn::make('created_at')
                    ->label('Cuándo')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(fn (array $data) => [
                        ...$data,
                        'user_id' => auth()->id(),
                    ])
                    ->after(function (Model $record): void {
                        /** @var Ticket $ticket */
                        $ticket = $this->getOwnerRecord();

                        // First public response from support triggers the SLA timer clear.
                        if (! $record->is_private && $ticket->first_responded_at === null && $record->user_id !== $ticket->requester_id) {
                            app(TicketService::class)->markFirstResponse($ticket);
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn ($record) => $record->user_id === auth()->id()),
                DeleteAction::make()
                    ->visible(fn ($record) => $record->user_id === auth()->id()),
            ]);
    }
}
