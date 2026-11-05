<?php

namespace App\Filament\Soporte\Pages;

use App\Filament\Pages\BaseSlaReport;

/**
 * Reporte de cumplimiento SLA en el panel /soporte.
 *
 * - super_admin / admin → ven todos los departamentos.
 * - supervisor_soporte  → solo su propio departamento (alcance aplicado
 *                          a todas las consultas en BaseSlaReport).
 * - agente / técnico    → no acceden.
 */
class SlaReport extends BaseSlaReport
{
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte']) ?? false;
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    protected function ticketViewRouteName(): string
    {
        return 'filament.soporte.resources.tickets.view';
    }

    /**
     * Admin y super_admin ven todo. Cualquier otro usuario queda limitado
     * a su departamento; si no tiene departamento se usa 0, que no
     * coincide con ninguno, para no exponer datos de otras áreas.
     */
    protected function departmentScopeId(): ?int
    {
        $user = auth()->user();

        if ($user?->hasAnyRole(['super_admin', 'admin'])) {
            return null;
        }

        return $user?->department_id ?? 0;
    }
}
