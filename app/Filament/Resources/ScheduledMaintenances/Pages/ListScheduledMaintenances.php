<?php

namespace App\Filament\Resources\ScheduledMaintenances\Pages;

use App\Filament\Resources\ScheduledMaintenances\ScheduledMaintenanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListScheduledMaintenances extends ListRecords
{
    protected static string $resource = ScheduledMaintenanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
