<?php

namespace App\Filament\Resources\SatisfactionSurveys\Pages;

use App\Filament\Resources\SatisfactionSurveys\SatisfactionSurveyResource;
use App\Filament\Resources\SatisfactionSurveys\Widgets\SurveyStatsWidget;
use Filament\Actions\Action;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListSatisfactionSurveys extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = SatisfactionSurveyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('surveyHelp')
                ->label('¿Cómo funcionan las encuestas?')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Encuestas de satisfacción')
                ->modalContent(view('filament.satisfaction-surveys.help', [
                    'autoPositiveDays' => (int) config('tickets.csat_auto_positive_days', 1),
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SurveyStatsWidget::make(['tablePageClass' => static::class]),
        ];
    }
}
