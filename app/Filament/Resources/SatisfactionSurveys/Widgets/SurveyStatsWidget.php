<?php

namespace App\Filament\Resources\SatisfactionSurveys\Widgets;

use App\Models\SatisfactionSurvey;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SurveyStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $total = SatisfactionSurvey::count();
        $responded = SatisfactionSurvey::whereNotNull('responded_at')->count();
        $pending = $total - $responded;
        $responseRate = $total > 0 ? round($responded / $total * 100) : 0;

        $avgGeneral = SatisfactionSurvey::whereNotNull('responded_at')->avg('rating') ?? 0;

        // Promedios por dimensión
        $dims = [
            'rating_attention' => 'Atención general',
            'rating_contact' => 'Facilidad de contacto',
            'rating_resolution' => 'Resolución',
            'rating_time' => 'Tiempo de solución',
            'rating_knowledge' => 'Conocimiento técnico',
            'rating_attitude' => 'Amabilidad',
        ];

        $dimStats = [];
        foreach ($dims as $field => $label) {
            $avg = SatisfactionSurvey::whereNotNull('responded_at')->whereNotNull($field)->avg($field) ?? 0;
            $dimStats[$label] = round($avg, 2);
        }
        arsort($dimStats);

        $bestDim = array_key_first($dimStats);
        $worstDim = array_key_last($dimStats);

        return [
            Stat::make('Total encuestas', $total)
                ->description("{$responded} respondidas · {$pending} pendientes")
                ->icon('heroicon-o-clipboard-document-list')
                ->color('primary'),

            Stat::make('Tasa de respuesta', "{$responseRate}%")
                ->description($responded.' de '.$total.' encuestas completadas')
                ->icon('heroicon-o-check-circle')
                ->color($responseRate >= 70 ? 'success' : ($responseRate >= 40 ? 'warning' : 'danger')),

            Stat::make('Promedio general', number_format($avgGeneral, 2).' / 5')
                ->description($responded > 0
                    ? 'Basado en '.$responded.' respuestas'
                    : 'Sin datos aún')
                ->icon('heroicon-o-star')
                ->color($avgGeneral >= 4 ? 'success' : ($avgGeneral >= 3 ? 'warning' : 'danger')),

            Stat::make('Mejor dimensión', $bestDim ?? '—')
                ->description(isset($dimStats[$bestDim]) ? number_format($dimStats[$bestDim], 2).' / 5' : '—')
                ->icon('heroicon-o-arrow-trending-up')
                ->color('success'),

            Stat::make('Área de mejora', $worstDim ?? '—')
                ->description(isset($dimStats[$worstDim]) ? number_format($dimStats[$worstDim], 2).' / 5' : '—')
                ->icon('heroicon-o-arrow-trending-down')
                ->color('warning'),
        ];
    }
}
