<?php

namespace App\Filament\Resources\SlaConfigs\Pages;

use App\Filament\Resources\SlaConfigs\SlaConfigResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSlaConfigs extends ListRecords
{
    protected static string $resource = SlaConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
