<?php

namespace App\Filament\Soporte\Resources\SatisfactionSurveys\Pages;

use App\Filament\Soporte\Resources\SatisfactionSurveys\SatisfactionSurveyResource;
use Filament\Resources\Pages\ViewRecord;

class ViewSatisfactionSurvey extends ViewRecord
{
    protected static string $resource = SatisfactionSurveyResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
