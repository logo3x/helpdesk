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
        // IMPORTANTE: usar getEloquentQuery() en lugar de Model::query()
        // para que el badge respete el scope por depto/asignación. De lo
        // contrario un supervisor de RRHH vería en el badge los tickets
        // de TI (y viceversa).
        return (string) static::getEloquentQuery()
            ->whereIn('status', [TicketStatus::Nuevo, TicketStatus::Reabierto])
            ->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        // CRÍTICO: el route binding debe respetar el mismo scope que el
        // listing. De lo contrario un agente podría abrir /soporte/tickets/{id}
        // de otro depto conociendo el ID. Reutilizamos getEloquentQuery().
        return static::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /**
     * Ticket visibility by role:
     *
     *   super_admin / admin → sees ALL tickets (no filter)
     *   supervisor_soporte  → only tickets of their own department
     *   agente_soporte / tecnico_campo → only tickets of their own
     *       department AND (assigned to them OR unassigned)
     *
     * Users without a department_id see nothing (by design:
     * agents/supervisors must always belong to a department).
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if (! $user || $user->hasAnyRole(['super_admin', 'admin'])) {
            return $query;
        }

        // Regla: un usuario staff SIEMPRE puede ver un ticket que le fue
        // asignado personalmente, aunque sea de otro depto. Si no fuera
        // así, un agente asignado por error o por transferencia cross-
        // depto quedaría con 404 en la notif y en el listado (bug
        // reportado por agente 2026-08-24).
        //
        // Resto de visibilidad:
        //   - supervisor: todos los tickets de su depto.
        //   - agente/técnico: tickets de su depto sin asignar (para
        //     poder tomarlos) + los asignados a otro pero de su depto
        //     no aplican — solo los suyos.
        return $query->where(function (Builder $q) use ($user) {
            // Siempre incluye los asignados personalmente.
            $q->where('assigned_to_id', $user->id);

            // Además, según rol, incluye del depto.
            if (! $user->department_id) {
                return;
            }

            if ($user->hasRole('supervisor_soporte')) {
                $q->orWhere('department_id', $user->department_id);
            } else {
                // Agente/técnico: tickets de su depto SIN asignar (para
                // poder tomarlos).
                $q->orWhere(function (Builder $sub) use ($user) {
                    $sub->where('department_id', $user->department_id)
                        ->whereNull('assigned_to_id');
                });
            }
        });
    }
}
