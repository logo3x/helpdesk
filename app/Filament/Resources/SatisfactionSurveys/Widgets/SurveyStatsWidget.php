<?php

namespace App\Filament\Resources\SatisfactionSurveys\Widgets;

use App\Filament\Concerns\HasHelpLabels;
use App\Filament\Resources\SatisfactionSurveys\Pages\ListSatisfactionSurveys;
use App\Filament\Resources\SatisfactionSurveys\Tables\SatisfactionSurveysTable;
use App\Models\SatisfactionSurvey;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Locked;

/**
 * KPIs de las encuestas. Se calculan sobre la consulta de la tabla de
 * la página, así que respetan los filtros y la búsqueda activos.
 */
class SurveyStatsWidget extends StatsOverviewWidget
{
    use HasHelpLabels;
    use InteractsWithPageTable;

    /**
     * Página de listado cuya tabla alimenta el widget (admin o soporte).
     */
    #[Locked]
    public string $tablePageClass = ListSatisfactionSurveys::class;

    protected function getTablePage(): string
    {
        return $this->tablePageClass;
    }

    protected function getStats(): array
    {
        $base = $this->getPageTableQuery()->reorder();

        $total = (clone $base)->count();
        $responded = (clone $base)->whereNotNull('responded_at')->count();
        $autoPositive = (clone $base)->whereNotNull('responded_at')
            ->where('comment', 'like', '%'.SatisfactionSurveysTable::AUTO_POSITIVE_MARK.'%')
            ->count();
        $realResponses = $responded - $autoPositive;
        $pending = $total - $responded;
        $responseRate = $total > 0 ? round($realResponses / $total * 100) : 0;

        $avgGeneral = (float) ((clone $base)->whereNotNull('responded_at')->avg('rating') ?? 0);

        $dimStats = [];
        foreach (SatisfactionSurvey::DIMENSIONS as $field => $label) {
            $avg = (clone $base)->whereNotNull($field)->avg($field);

            if ($avg !== null) {
                $dimStats[$this->shortDimensionLabel($field, $label)] = round((float) $avg, 2);
            }
        }
        arsort($dimStats);

        $bestDim = array_key_first($dimStats);
        $worstDim = array_key_last($dimStats);

        return [
            Stat::make(static::helpLabel('Total encuestas', 'Encuestas enviadas que coinciden con los filtros actuales. Se envían al cerrar un ticket.'), $total)
                ->description("{$responded} respondidas · {$pending} pendientes")
                ->icon('heroicon-o-clipboard-document-list')
                ->color('primary'),

            Stat::make(static::helpLabel('Tasa de respuesta', 'Porcentaje de encuestas que el usuario respondió. No cuenta las auto-positivas, porque esas no las respondió nadie.'), "{$responseRate}%")
                ->description("{$realResponses} de {$total} respondidas por el usuario")
                ->icon('heroicon-o-check-circle')
                ->color($responseRate >= 70 ? 'success' : ($responseRate >= 40 ? 'warning' : 'danger')),

            Stat::make(static::helpLabel('Promedio general', 'Promedio de la calificación (1 a 5) de las encuestas respondidas. Incluye las auto-positivas, que cuentan como 5. Filtra por «Respondida por el usuario» para ver solo respuestas reales.'), number_format($avgGeneral, 2).' / 5')
                ->description($responded > 0
                    ? "{$realResponses} reales · {$autoPositive} auto-positivas"
                    : 'Sin datos aún')
                ->icon('heroicon-o-star')
                ->color($avgGeneral >= 4 ? 'success' : ($avgGeneral >= 3 ? 'warning' : 'danger')),

            Stat::make(static::helpLabel('Mejor dimensión', 'La pregunta con el promedio más alto. Solo cuenta respuestas reales, porque las auto-positivas no califican las preguntas.'), $bestDim ?? '—')
                ->description(isset($dimStats[$bestDim]) ? number_format($dimStats[$bestDim], 2).' / 5' : '—')
                ->icon('heroicon-o-arrow-trending-up')
                ->color('success'),

            Stat::make(static::helpLabel('Área de mejora', 'La pregunta con el promedio más bajo: el aspecto del servicio que más conviene reforzar.'), $worstDim ?? '—')
                ->description(isset($dimStats[$worstDim]) ? number_format($dimStats[$worstDim], 2).' / 5' : '—')
                ->icon('heroicon-o-arrow-trending-down')
                ->color('warning'),
        ];
    }

    protected function shortDimensionLabel(string $field, string $label): string
    {
        return match ($field) {
            'rating_resolution' => 'Resolución',
            'rating_knowledge' => 'Conocimiento técnico',
            'rating_attitude' => 'Amabilidad',
            default => $label,
        };
    }
}
