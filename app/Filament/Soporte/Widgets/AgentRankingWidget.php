<?php

namespace App\Filament\Soporte\Widgets;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Ranking de agentes por productividad — últimos 30 días.
 *
 * Métricas:
 *  - Resueltos: tickets cerrados/resueltos en los últimos 30 días.
 *  - CSAT prom: promedio del rating de las encuestas de satisfacción
 *    de los tickets que el agente resolvió.
 *  - Tiempo medio: minutos promedio entre asignación y resolución.
 *
 * Visible solo para super_admin / admin / supervisor_soporte. El
 * supervisor ve solo agentes de su propio departamento.
 */
class AgentRankingWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 99;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->buildQuery())
            ->heading('Ranking de agentes (últimos 30 días)')
            ->description('Productividad medida por tickets resueltos, CSAT promedio y tiempo medio de resolución.')
            ->paginated([10])
            ->columns([
                TextColumn::make('name')
                    ->label('Agente')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('resolved_count')
                    ->label('Resueltos')
                    ->sortable()
                    ->alignEnd()
                    ->badge()
                    ->color('success'),
                TextColumn::make('csat_avg')
                    ->label('CSAT prom')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 1).'/5' : '—'),
            ])
            ->defaultSort('resolved_count', 'desc');
    }

    /**
     * Construye la query base de agentes con sus métricas agregadas.
     *
     * @return Builder<User>
     */
    protected function buildQuery(): Builder
    {
        $authUser = auth()->user();
        $isAdmin = $authUser?->hasAnyRole(['super_admin', 'admin']) ?? false;
        $since = now()->subDays(30);

        $query = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['agente_soporte', 'tecnico_campo', 'supervisor_soporte']));

        // Supervisor solo ve agentes de su propio departamento.
        if (! $isAdmin && $authUser?->department_id) {
            $query->where('department_id', $authUser->department_id);
        }

        return $query
            ->select('users.*')
            ->selectSub(
                Ticket::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('assigned_to_id', 'users.id')
                    ->whereIn('status', [TicketStatus::Resuelto, TicketStatus::Cerrado])
                    ->where('resolved_at', '>=', $since)
                    ->toBase(),
                'resolved_count'
            )
            ->selectSub(
                DB::table('satisfaction_surveys')
                    ->selectRaw('AVG(rating)')
                    ->join('tickets', 'tickets.id', '=', 'satisfaction_surveys.ticket_id')
                    ->whereColumn('tickets.assigned_to_id', 'users.id')
                    ->whereNotNull('satisfaction_surveys.rating')
                    ->where('satisfaction_surveys.responded_at', '>=', $since),
                'csat_avg'
            );
    }

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte']) ?? false;
    }
}
