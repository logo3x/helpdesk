<?php

namespace App\Jobs;

use App\Enums\MaintenanceStatus;
use App\Models\ScheduledMaintenance;
use App\Notifications\ScheduledMaintenanceDueNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Corre diariamente (routes/console.php) y notifica al agente asignado
 * cuando faltan MAINTENANCE_ALERT_DAYS_BEFORE (default 30) días o menos
 * para la fecha del mantenimiento pendiente.
 *
 * Usa la columna notified_due_at para no repetir la alerta si el job
 * corre varias veces el mismo día (o si la BD queue retriea).
 */
class NotifyDueMaintenancesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $daysBefore = (int) config('maintenances.alert_days_before', 30);
        $threshold = now()->addDays($daysBefore)->endOfDay();

        $due = ScheduledMaintenance::query()
            ->where('status', MaintenanceStatus::Pendiente->value)
            ->whereNull('notified_due_at')
            ->where('scheduled_at', '<=', $threshold)
            ->with('agent', 'asset')
            ->get();

        foreach ($due as $maintenance) {
            if (! $maintenance->agent) {
                continue;
            }

            $maintenance->agent->notify(new ScheduledMaintenanceDueNotification($maintenance));

            $maintenance->forceFill(['notified_due_at' => now()])->save();
        }
    }
}
