<?php

namespace App\Filament\Pages;

/**
 * Reporte de cumplimiento SLA del panel /admin: todos los departamentos.
 *
 * La lógica vive en BaseSlaReport; aquí solo se define el panel.
 */
class SlaReport extends BaseSlaReport
{
    protected static string|\UnitEnum|null $navigationGroup = null;

    protected function ticketViewRouteName(): string
    {
        return 'filament.admin.resources.tickets.view';
    }
}
