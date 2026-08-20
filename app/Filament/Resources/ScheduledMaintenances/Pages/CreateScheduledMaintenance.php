<?php

namespace App\Filament\Resources\ScheduledMaintenances\Pages;

use App\Filament\Resources\ScheduledMaintenances\ScheduledMaintenanceResource;
use App\Filament\Soporte\Resources\ScheduledMaintenances\Pages\CreateScheduledMaintenance as SoporteCreateScheduledMaintenance;

/**
 * Panel admin: hereda la lógica de creación del panel Soporte
 * (fuerza created_by_id y notifica al agente) y solo cambia el
 * resource al que apunta para que la ruta resuelva bajo /admin/*.
 */
class CreateScheduledMaintenance extends SoporteCreateScheduledMaintenance
{
    protected static string $resource = ScheduledMaintenanceResource::class;
}
