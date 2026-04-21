<?php

namespace App\Filament\Resources\Tickets;

use App\Enums\TicketStatus;
use App\Filament\Resources\Tickets\Pages\ListTickets;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
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

/**
 * Tickets Resource en el panel /admin (read-only listado global).
 *
 * A diferencia del panel /soporte (donde los tickets se filtran por
 * departamento y por asignación), aquí el super_admin / admin ven
 * TODOS los tickets de TODOS los departamentos sin filtros.
 *
 * Las acciones operativas (crear, asignar, resolver) siguen siendo
 * exclusivas del panel /soporte para no duplicar lógica — desde aquí
 * solo se listan, filtran, exportan y se puede abrir el detalle.
 */
class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?string $modelLabel = 'Ticket';

    protected static ?string $pluralModelLabel = 'Tickets (todos)';

    protected static ?string $recordTitleAttribute = 'number';

    protected static string|\UnitEnum|null $navigationGroup = 'Operación';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return TicketForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        // Reutilizamos la tabla del panel Soporte — mismo formato,
        // columnas y filtros.
        return TicketsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTickets::route('/'),
            'view' => ViewTicket::route('/{record}'),
        ];
    }

    /**
     * Admin y super_admin ven TODOS los tickets, sin scope por depto.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return static::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function canCreate(): bool
    {
        // La creación se hace en /soporte; aquí solo se consulta.
        return false;
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
}
