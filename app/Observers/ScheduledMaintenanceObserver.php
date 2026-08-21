<?php

namespace App\Observers;

use App\Enums\MaintenanceStatus;
use App\Models\AssetHistory;
use App\Models\ScheduledMaintenance;

/**
 * Reacciona al ciclo de vida de un ScheduledMaintenance.
 *
 * Al cerrar un mantenimiento (transición pendiente → cumplido | no_cumplido):
 *   1. Registra el evento en assets.histories (columna asset_history)
 *      con las observaciones del mantenimiento.
 *   2. Si es cumplido, actualiza el propio Asset (last_maintenance_at,
 *      maintenance_interval_days) — el observer del Asset recalcula
 *      next_maintenance_at.
 *   3. Auto-genera la siguiente ocurrencia con scheduled_at = fecha
 *      original + días de la frecuencia. Ambos casos (cumplido y
 *      no_cumplido) generan siguiente para mantener el ciclo activo.
 */
class ScheduledMaintenanceObserver
{
    public function updating(ScheduledMaintenance $maintenance): void
    {
        if (! $maintenance->isDirty('status')) {
            return;
        }

        $previousStatus = $maintenance->getOriginal('status');
        $newStatus = $maintenance->status;

        $previousEnum = $previousStatus instanceof MaintenanceStatus
            ? $previousStatus
            : ($previousStatus !== null ? MaintenanceStatus::tryFrom((string) $previousStatus) : null);

        $isClosingNow = $previousEnum === MaintenanceStatus::Pendiente
            && $newStatus instanceof MaintenanceStatus
            && $newStatus->isClosed();

        if (! $isClosingNow) {
            return;
        }

        // Timestamp automático al cerrar; no forzamos si el usuario ya lo puso.
        if ($maintenance->completed_at === null) {
            $maintenance->completed_at = now();
        }
    }

    public function updated(ScheduledMaintenance $maintenance): void
    {
        $statusChanged = $maintenance->wasChanged('status');
        if (! $statusChanged) {
            return;
        }

        if (! $maintenance->status instanceof MaintenanceStatus || ! $maintenance->status->isClosed()) {
            return;
        }

        // Guardarraíl anti-duplicación: solo disparamos los efectos
        // secundarios (log en historial + siguiente ocurrencia + update
        // del asset) cuando el mantenimiento pasa de PENDIENTE a cerrado.
        // Si venía cerrado y ahora cambió a otro estado cerrado (ej.
        // cumplido → no_cumplido por corrección) NO regeneramos ciclo
        // — el hijo ya existe. Solo dejamos anotación diferencial.
        $previousStatus = $maintenance->getOriginal('status');
        $previousEnum = $previousStatus instanceof MaintenanceStatus
            ? $previousStatus
            : ($previousStatus !== null ? MaintenanceStatus::tryFrom((string) $previousStatus) : null);

        if ($previousEnum !== MaintenanceStatus::Pendiente) {
            // Sí registramos que se editó el estado (para trazabilidad),
            // pero sin generar hijo ni actualizar asset. Es una corrección
            // manual, no un nuevo ciclo de trabajo.
            $this->logInAssetHistory($maintenance);

            return;
        }

        $this->logInAssetHistory($maintenance);

        if ($maintenance->status === MaintenanceStatus::Cumplido) {
            $this->updateAssetMaintenanceFields($maintenance);
        }

        $this->generateNextOccurrence($maintenance);
    }

    protected function logInAssetHistory(ScheduledMaintenance $maintenance): void
    {
        $asset = $maintenance->asset;
        if (! $asset) {
            return;
        }

        $notesLines = array_filter([
            'Mantenimiento programado #'.$maintenance->id,
            'Estado: '.$maintenance->status->label(),
            $maintenance->progress_percent ? 'Avance: '.$maintenance->progress_percent.'%' : null,
            $maintenance->observations ? 'Observaciones: '.$maintenance->observations : null,
            $maintenance->not_completed_reason ? 'Motivo no cumplido: '.$maintenance->not_completed_reason : null,
        ]);

        AssetHistory::create([
            'asset_id' => $asset->id,
            'user_id' => $maintenance->agent_id,
            'action' => 'maintenance',
            'notes' => implode("\n", $notesLines),
        ]);
    }

    protected function updateAssetMaintenanceFields(ScheduledMaintenance $maintenance): void
    {
        $asset = $maintenance->asset;
        if (! $asset) {
            return;
        }

        // Skip auto-history del Asset: ya escribimos el registro específico
        // arriba con las observaciones del mantenimiento; sino saldrían dos
        // entradas por el mismo cierre.
        $asset->skipAutoHistory = true;
        $asset->forceFill([
            'last_maintenance_at' => $maintenance->completed_at ?? now(),
            'maintenance_interval_days' => $maintenance->frequencyDays(),
        ])->save();
    }

    protected function generateNextOccurrence(ScheduledMaintenance $maintenance): void
    {
        // Guardarraíl: no auto-generar si ya hay un hijo (evita loop en
        // updates duplicados o al restaurar un soft delete).
        $alreadyChained = ScheduledMaintenance::query()
            ->where('parent_id', $maintenance->id)
            ->exists();

        if ($alreadyChained) {
            return;
        }

        $days = $maintenance->frequencyDays();
        if ($days === 0) {
            return;
        }

        // La siguiente ocurrencia se agenda en base a la fecha ORIGINAL
        // programada + los días de la frecuencia — mantiene el ciclo
        // aunque el actual se haya cerrado tarde o como no_cumplido.
        $nextDate = $maintenance->scheduled_at->copy()->addDays($days);

        ScheduledMaintenance::create([
            'asset_id' => $maintenance->asset_id,
            'agent_id' => $maintenance->agent_id,
            'created_by_id' => $maintenance->agent_id,
            'parent_id' => $maintenance->id,
            'scheduled_at' => $nextDate,
            'status' => MaintenanceStatus::Pendiente,
            'progress_percent' => 0,
            'frequency' => $maintenance->frequency,
        ]);
    }
}
