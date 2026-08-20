<?php

namespace App\Filament\Soporte\Widgets;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Tendencia semanal de tickets resueltos — top 5 agentes de las
 * últimas 8 semanas. Una línea por agente, eje X = semanas ISO,
 * eje Y = tickets resueltos.
 *
 * Solo super_admin/admin/supervisor. El supervisor ve los agentes
 * de su propio depto.
 */
class AgentTrendChart extends ChartWidget
{
    protected ?string $heading = 'Tendencia semanal — tickets resueltos por agente (top 5)';

    protected ?string $description = 'Últimas 8 semanas. Cada línea es un agente. Compara ritmo entre agentes.';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 100;

    /** Paleta consistente para las 5 líneas — accesible en light+dark. */
    protected const COLORS = [
        '#2563eb', // blue
        '#16a34a', // green
        '#d97706', // amber
        '#dc2626', // red
        '#7c3aed', // violet
    ];

    protected function getData(): array
    {
        $weeksBack = 8;
        $sinceStr = now()->startOfWeek()->subWeeks($weeksBack - 1)->toDateTimeString();

        // 1. Top 5 agentes por resueltos en la ventana.
        $authUser = auth()->user();
        $isAdmin = $authUser?->hasAnyRole(['super_admin', 'admin']) ?? false;

        $topAgentsQuery = Ticket::query()
            ->selectRaw('assigned_to_id, COUNT(*) as total')
            ->whereNotNull('assigned_to_id')
            ->whereIn('status', [TicketStatus::Resuelto, TicketStatus::Cerrado])
            ->where('resolved_at', '>=', $sinceStr)
            ->groupBy('assigned_to_id')
            ->orderByDesc('total')
            ->limit(5);

        if (! $isAdmin && $authUser?->department_id) {
            $topAgentsQuery->where('department_id', $authUser->department_id);
        }

        $topAgentIds = $topAgentsQuery->pluck('assigned_to_id')->all();

        if ($topAgentIds === []) {
            return [
                'labels' => [],
                'datasets' => [],
            ];
        }

        // 2. Etiquetas de las últimas 8 semanas ("Sem 34", "Sem 35"...).
        $labels = [];
        $weekStarts = [];
        for ($i = $weeksBack - 1; $i >= 0; $i--) {
            $start = now()->startOfWeek()->subWeeks($i);
            $labels[] = 'Sem '.$start->weekOfYear;
            $weekStarts[] = $start;
        }

        // 3. Resueltos por (agente, semana) — un solo query group by.
        $agents = User::whereIn('id', $topAgentIds)->get()->keyBy('id');
        $counts = Ticket::query()
            ->selectRaw('assigned_to_id, '.self::weekExpr('resolved_at').' as week_key, COUNT(*) as total')
            ->whereIn('assigned_to_id', $topAgentIds)
            ->whereIn('status', [TicketStatus::Resuelto, TicketStatus::Cerrado])
            ->where('resolved_at', '>=', $sinceStr)
            ->groupBy('assigned_to_id', 'week_key')
            ->get()
            ->groupBy('assigned_to_id');

        // 4. Un dataset por agente con la serie alineada a los labels.
        $datasets = [];
        $colorIdx = 0;
        foreach ($topAgentIds as $agentId) {
            $agent = $agents[$agentId] ?? null;
            if (! $agent) {
                continue;
            }
            $agentCounts = $counts[$agentId] ?? collect();
            $indexed = $agentCounts->keyBy('week_key');

            $series = [];
            foreach ($weekStarts as $start) {
                $key = self::weekKeyFor($start);
                $series[] = (int) ($indexed[$key]->total ?? 0);
            }

            $color = self::COLORS[$colorIdx % count(self::COLORS)];
            $datasets[] = [
                'label' => $agent->name,
                'data' => $series,
                'borderColor' => $color,
                'backgroundColor' => $color.'33', // alpha 20%
                'tension' => 0.3,
                'borderWidth' => 2,
                'pointRadius' => 3,
            ];
            $colorIdx++;
        }

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    /**
     * Expresión SQL portable (SQLite/MySQL) que devuelve "YYYY-WW" para
     * agrupar por semana ISO.
     */
    protected static function weekExpr(string $column): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => "strftime('%Y-%W', {$column})",
            'mysql', 'mariadb' => "DATE_FORMAT({$column}, '%Y-%u')",
            default => "strftime('%Y-%W', {$column})",
        };
    }

    /** Mismo formato "YYYY-WW" para casar con las agrupaciones. */
    protected static function weekKeyFor(CarbonInterface $date): string
    {
        return $date->format('Y').'-'.str_pad((string) $date->weekOfYear, 2, '0', STR_PAD_LEFT);
    }

    protected function getType(): string
    {
        return 'line';
    }

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte']) ?? false;
    }
}
