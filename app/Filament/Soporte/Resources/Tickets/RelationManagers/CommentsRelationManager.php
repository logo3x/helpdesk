<?php

namespace App\Filament\Soporte\Resources\Tickets\RelationManagers;

use App\Models\CannedResponse;
use App\Models\Ticket;
use App\Notifications\TicketCommentedNotification;
use App\Services\TicketService;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $title = 'Comentarios';

    /**
     * Filament v5 marca los RelationManagers como read-only cuando se
     * renderizan dentro de una ViewRecord (caso ViewTicket). Eso oculta
     * el botón "Nuevo comentario" y bloquea EditAction/DeleteAction.
     *
     * Aquí queremos lo contrario: el flujo principal de un agente es
     * comentar el ticket DESDE la vista (no desde edit), así que
     * forzamos que sea editable. La autorización fina se sigue
     * delegando a TicketCommentPolicy.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('_canned_response_id')
                    ->label('Respuesta predefinida')
                    ->helperText('Inserta una plantilla de respuesta rápida. Puedes editarla después.')
                    ->options(function () {
                        $user = auth()->user();
                        $query = CannedResponse::query()->where('is_active', true);

                        if ($user && ! $user->hasAnyRole(['super_admin', 'admin']) && $user->department_id) {
                            $query->where(function ($q) use ($user) {
                                $q->whereHas('category', fn ($sub) => $sub->where('department_id', $user->department_id))
                                    ->orWhereNull('category_id');
                            });
                        }

                        return $query->orderBy('sort_order')->pluck('title', 'id')->all();
                    })
                    ->dehydrated(false)
                    ->live()
                    ->searchable()
                    ->placeholder('— Sin respuesta predefinida —')
                    ->afterStateUpdated(function ($state, Set $set): void {
                        if (! $state) {
                            return;
                        }

                        $canned = CannedResponse::find($state);
                        if ($canned) {
                            $set('body', $canned->body);
                        }
                    })
                    ->columnSpanFull(),

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
                    ->color(fn ($record) => $record->is_system_event ? 'gray' : 'primary')
                    ->formatStateUsing(fn ($record, $state) => $record->is_system_event
                        ? '⚙ Sistema'
                        : ($state ?? '—')
                    ),
                TextColumn::make('body')
                    ->label('Comentario')
                    ->wrap()
                    ->limit(140)
                    ->color(fn ($record) => $record->is_system_event ? 'gray' : null)
                    ->fontFamily(fn ($record) => $record->is_system_event ? 'mono' : null),
                IconColumn::make('is_private')
                    ->label('Interno')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-eye')
                    ->trueColor('warning')
                    ->falseColor('success')
                    ->visible(fn ($record) => ! ($record?->is_system_event ?? false)),
                TextColumn::make('event_type')
                    ->label('Evento')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'transferred' => '🔄 Trasladado',
                        'assigned' => '👤 Asignado',
                        'resolved' => '✓ Resuelto',
                        'reopened' => '↩ Reabierto',
                        'recalibrated' => '⚖ Recalibrado',
                        default => $state,
                    })
                    ->placeholder('—')
                    ->toggleable(),
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

                        // Notify the requester about public comments from agents.
                        if (! $record->is_private && $record->user_id !== $ticket->requester_id) {
                            $ticket->requester->notify(new TicketCommentedNotification($ticket, $record));
                        }
                    }),
            ])
            ->recordActions([
                // Los eventos del sistema no se pueden editar ni borrar:
                // son traza de auditoría, su valor está en quedar fijos.
                EditAction::make()
                    ->visible(fn ($record) => ! $record->is_system_event && $record->user_id === auth()->id()),
                DeleteAction::make()
                    ->visible(fn ($record) => ! $record->is_system_event && $record->user_id === auth()->id()),
            ]);
    }
}
