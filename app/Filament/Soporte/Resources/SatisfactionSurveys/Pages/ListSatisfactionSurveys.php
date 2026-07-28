<?php

namespace App\Filament\Soporte\Resources\SatisfactionSurveys\Pages;

use App\Filament\Resources\SatisfactionSurveys\Widgets\SurveyStatsWidget;
use App\Filament\Soporte\Resources\SatisfactionSurveys\SatisfactionSurveyResource;
use Filament\Resources\Pages\ListRecords;

class ListSatisfactionSurveys extends ListRecords
{
    protected static string $resource = SatisfactionSurveyResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SurveyStatsWidget::class,
        ];
    }
}
