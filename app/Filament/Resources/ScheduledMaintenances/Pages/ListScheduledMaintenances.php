<?php

namespace App\Filament\Resources\ScheduledMaintenances\Pages;

use App\Filament\Resources\ScheduledMaintenances\ScheduledMaintenanceResource;
use App\Filament\Soporte\Widgets\MaintenancesKpiWidget;
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

    /**
     * Mismo widget de KPIs que el panel Soporte (compartido).
     */
    protected function getHeaderWidgets(): array
    {
        return [
            MaintenancesKpiWidget::class,
        ];
    }
}
