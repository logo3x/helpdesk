<?php

namespace App\Filament\Resources\ScheduledMaintenances\Pages;

use App\Filament\Resources\ScheduledMaintenances\ScheduledMaintenanceResource;
use App\Filament\Soporte\Resources\ScheduledMaintenances\Pages\EditScheduledMaintenance as SoporteEditScheduledMaintenance;

/**
 * Panel admin: hereda la lógica de edición del panel Soporte
 * (notifica si cambia el agente asignado) apuntando al resource admin.
 */
class EditScheduledMaintenance extends SoporteEditScheduledMaintenance
{
    protected static string $resource = ScheduledMaintenanceResource::class;
}
