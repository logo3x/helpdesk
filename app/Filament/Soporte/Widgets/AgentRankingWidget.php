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
use Illuminate\Support\HtmlString;

/**
 * Ranking de agentes por productividad — últimos 30 días.
 *
 * Métricas expandidas (Sprint 3):
 *  - Resueltos: tickets cerrados/resueltos en ventana.
 *  - Abiertos ahora: tickets asignados en estados abiertos (backlog).
 *  - Tiempo primera resp: promedio de business minutes entre
 *    ticket.created_at y first_responded_at.
 *  - Tiempo resolución: promedio de business minutes entre
 *    first_responded_at y resolved_at (o created_at si no hay primera
 *    respuesta) menos paused_minutes.
 *  - CSAT prom: promedio del rating de encuestas.
 *  - % Reaperturas: qué proporción de sus resueltos volvió a
 *    Reabierto — indica calidad de la resolución.
 *  - % Cumpl SLA: cumplimiento de resolution_due_at.
 *
 * Visible solo para super_admin/admin/supervisor_soporte. El
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
            ->description('Productividad, calidad y cumplimiento de SLA. Ordenado por resueltos.')
            ->paginated([10, 25])
            ->columns([
                TextColumn::make('name')
                    ->label('Agente')
                    ->searchable()
                    ->sortable()
                    ->description(fn (User $r) => $r->department?->name),

                TextColumn::make('resolved_count')
                    ->label('Resueltos')
                    ->sortable()
                    ->alignEnd()
                    ->badge()
                    ->color('success'),

                TextColumn::make('open_count')
                    ->label('Abiertos ahora')
                    ->sortable()
                    ->alignEnd()
                    ->badge()
                    ->color(fn ($state) => (int) $state > 10 ? 'danger' : ((int) $state > 5 ? 'warning' : 'gray')),

                TextColumn::make('first_response_avg_minutes')
                    ->label('1a resp. (prom)')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => self::formatMinutes($state)),

                TextColumn::make('resolution_avg_minutes')
                    ->label('Resolución (prom)')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => self::formatMinutes($state)),

                TextColumn::make('csat_avg')
                    ->label('CSAT')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => $state !== null
                        ? number_format((float) $state, 1).' / 5'
                        : '—')
                    ->color(fn ($state) => match (true) {
                        $state === null => 'gray',
                        (float) $state >= 4.5 => 'success',
                        (float) $state >= 3.5 => 'warning',
                        default => 'danger',
                    }),

                TextColumn::make('reopened_rate')
                    ->label('% Reaper.')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => $state !== null ? round((float) $state, 1).'%' : '—')
                    ->color(fn ($state) => match (true) {
                        $state === null => 'gray',
                        (float) $state === 0.0 => 'success',
                        (float) $state <= 10 => 'warning',
                        default => 'danger',
                    })
                    ->tooltip('Porcentaje de tickets que este agente resolvió y que fueron reabiertos por el solicitante. Menor es mejor.'),

                TextColumn::make('sla_compliance_pct')
                    ->label('% SLA')
                    ->alignEnd()
                    ->formatStateUsing(function ($state) {
                        if ($state === null) {
                            return '—';
                        }
                        $pct = (int) round((float) $state);
                        $color = $pct >= 90 ? '#16a34a' : ($pct >= 70 ? '#d97706' : '#dc2626');

                        return new HtmlString(sprintf(
                            '<div style="display:inline-flex;align-items:center;gap:0.5rem;justify-content:flex-end;min-width:110px;">'
                            .'<span style="flex:1;height:6px;background:rgb(228 228 231);border-radius:3px;overflow:hidden;">'
                            .'<span style="display:block;height:100%%;width:%d%%;background:%s;"></span>'
                            .'</span>'
                            .'<span style="font-weight:600;color:%s;min-width:32px;text-align:right;">%d%%</span>'
                            .'</div>',
                            min(100, max(0, $pct)),
                            $color,
                            $color,
                            $pct,
                        ));
                    })
                    ->html()
                    ->tooltip('Porcentaje de tickets resueltos dentro del plazo de SLA. Verde ≥90%, ámbar ≥70%, rojo <70%.'),
            ])
            ->defaultSort('resolved_count', 'desc');
    }

    /**
     * @return Builder<User>
     */
    protected function buildQuery(): Builder
    {
        $authUser = auth()->user();
        $isAdmin = $authUser?->hasAnyRole(['super_admin', 'admin']) ?? false;
        $since = now()->subDays(30);
        $sinceStr = $since->toDateTimeString();

        $query = User::query()
            ->with('department:id,name')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['agente_soporte', 'tecnico_campo', 'supervisor_soporte']));

        if (! $isAdmin && $authUser?->department_id) {
            $query->where('department_id', $authUser->department_id);
        }

        // Los subqueries corren contra la BD directa (toBase) y comparan
        // con users.id para producir una columna agregada por agente.
        return $query
            ->select('users.*')
            ->selectSub(
                Ticket::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('assigned_to_id', 'users.id')
                    ->whereIn('status', [TicketStatus::Resuelto, TicketStatus::Cerrado])
                    ->where('resolved_at', '>=', $sinceStr)
                    ->toBase(),
                'resolved_count'
            )
            ->selectSub(
                Ticket::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('assigned_to_id', 'users.id')
                    ->whereIn('status', [
                        TicketStatus::Nuevo,
                        TicketStatus::Asignado,
                        TicketStatus::EnProgreso,
                        TicketStatus::PendienteCliente,
                        TicketStatus::Reabierto,
                    ])
                    ->toBase(),
                'open_count'
            )
            ->selectSub(
                // Minutos calendario promedio entre created_at y first_responded_at.
                // Usar strftime para SQLite; en MySQL sería TIMESTAMPDIFF.
                Ticket::query()
                    ->selectRaw(self::avgMinutesExpr('created_at', 'first_responded_at'))
                    ->whereColumn('assigned_to_id', 'users.id')
                    ->whereNotNull('first_responded_at')
                    ->where('first_responded_at', '>=', $sinceStr)
                    ->toBase(),
                'first_response_avg_minutes'
            )
            ->selectSub(
                Ticket::query()
                    ->selectRaw(self::avgMinutesExpr('created_at', 'resolved_at').' - COALESCE(AVG(paused_minutes), 0)')
                    ->whereColumn('assigned_to_id', 'users.id')
                    ->whereNotNull('resolved_at')
                    ->where('resolved_at', '>=', $sinceStr)
                    ->toBase(),
                'resolution_avg_minutes'
            )
            ->selectSub(
                DB::table('satisfaction_surveys')
                    ->selectRaw('AVG(rating)')
                    ->join('tickets', 'tickets.id', '=', 'satisfaction_surveys.ticket_id')
                    ->whereColumn('tickets.assigned_to_id', 'users.id')
                    ->whereNotNull('satisfaction_surveys.rating')
                    ->where('satisfaction_surveys.responded_at', '>=', $sinceStr),
                'csat_avg'
            )
            ->selectSub(
                // % reaperturas: reopened_at NOT NULL / total resueltos * 100
                Ticket::query()
                    ->selectRaw('CASE WHEN COUNT(*) = 0 THEN NULL ELSE (SUM(CASE WHEN reopened_at IS NOT NULL THEN 1 ELSE 0 END) * 100.0 / COUNT(*)) END')
                    ->whereColumn('assigned_to_id', 'users.id')
                    ->whereIn('status', [TicketStatus::Resuelto, TicketStatus::Cerrado])
                    ->where('resolved_at', '>=', $sinceStr)
                    ->toBase(),
                'reopened_rate'
            )
            ->selectSub(
                Ticket::query()
                    ->selectRaw('CASE WHEN COUNT(*) = 0 THEN NULL ELSE (SUM(CASE WHEN resolution_breached = 0 OR resolution_breached IS NULL THEN 1 ELSE 0 END) * 100.0 / COUNT(*)) END')
                    ->whereColumn('assigned_to_id', 'users.id')
                    ->whereIn('status', [TicketStatus::Resuelto, TicketStatus::Cerrado])
                    ->where('resolved_at', '>=', $sinceStr)
                    ->whereNotNull('sla_config_id')
                    ->toBase(),
                'sla_compliance_pct'
            );
    }

    /**
     * Expresión SQL portable (SQLite + MySQL) para calcular el
     * promedio de minutos entre dos columnas datetime.
     */
    protected static function avgMinutesExpr(string $from, string $to): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => "AVG((julianday({$to}) - julianday({$from})) * 24 * 60)",
            'mysql', 'mariadb' => "AVG(TIMESTAMPDIFF(MINUTE, {$from}, {$to}))",
            default => "AVG((julianday({$to}) - julianday({$from})) * 24 * 60)",
        };
    }

    /**
     * Formatea minutos como "Xh Ym" / "Xd Yh" / "Xm" según magnitud.
     */
    protected static function formatMinutes(mixed $state): string
    {
        if ($state === null) {
            return '—';
        }
        $minutes = max(0, (int) round((float) $state));

        if ($minutes === 0) {
            return '0 min';
        }
        if ($minutes < 60) {
            return $minutes.' min';
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        if ($hours < 24) {
            return $mins > 0 ? "{$hours}h {$mins}m" : "{$hours}h";
        }

        $days = intdiv($hours, 24);
        $remHours = $hours % 24;

        return $remHours > 0 ? "{$days}d {$remHours}h" : "{$days}d";
    }

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte']) ?? false;
    }
}
