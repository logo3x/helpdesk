<?php

namespace App\Filament\Soporte\Resources\Tickets;

use App\Enums\TicketStatus;
use App\Filament\Soporte\Resources\Tickets\Pages\CreateTicket;
use App\Filament\Soporte\Resources\Tickets\Pages\EditTicket;
use App\Filament\Soporte\Resources\Tickets\Pages\ListTickets;
use App\Filament\Soporte\Resources\Tickets\Pages\ViewTicket;
use App\Filament\Soporte\Resources\Tickets\RelationManagers\CommentsRelationManager;
use App\Filament\Soporte\Resources\Tickets\Schemas\TicketForm;
use App\Filament\Soporte\Resources\Tickets\Tables\TicketsTable;
use App\Models\Ticket;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?string $modelLabel = 'Ticket';

    protected static ?string $pluralModelLabel = 'Tickets';

    protected static ?string $recordTitleAttribute = 'number';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return TicketForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TicketsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            CommentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTickets::route('/'),
            'create' => CreateTicket::route('/create'),
            'view' => ViewTicket::route('/{record}'),
            'edit' => EditTicket::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::query()
            ->whereIn('status', [TicketStatus::Nuevo, TicketStatus::Reabierto])
            ->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /**
     * Restrict the tickets list for agente_soporte / tecnico_campo:
     * they only see tickets assigned to them OR unassigned tickets
     * (so they can pick them up). Supervisors and admins see everything.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if ($user && ! $user->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte'])) {
            $query->where(function (Builder $q) use ($user) {
                $q->where('assigned_to_id', $user->id)
                    ->orWhereNull('assigned_to_id');
            });
        }

        return $query;
    }
}
