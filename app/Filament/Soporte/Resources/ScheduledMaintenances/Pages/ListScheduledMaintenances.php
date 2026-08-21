<?php

namespace App\Filament\Soporte\Resources\ScheduledMaintenances\Pages;

use App\Filament\Soporte\Resources\ScheduledMaintenances\ScheduledMaintenanceResource;
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
     * KPIs del módulo encima de la tabla: programados, pendientes,
     * vencidos, próximos 30 días, cumplidos y no cumplidos del mes.
     */
    protected function getHeaderWidgets(): array
    {
        return [
            MaintenancesKpiWidget::class,
        ];
    }
}
