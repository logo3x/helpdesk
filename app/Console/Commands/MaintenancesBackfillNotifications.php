<?php

namespace App\Console\Commands;

use App\Jobs\NotifyDueMaintenancesJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * One-shot para disparar el flujo de alertas de vencimiento sin
 * esperar al schedule diario (7:15am). Útil justo después del
 * primer deploy del módulo, para que los mantenimientos ya
 * programados con fecha dentro de MAINTENANCE_ALERT_DAYS_BEFORE
 * reciban su notificación de inmediato.
 *
 * Es idempotente: solo notifica los que aún tienen notified_due_at
 * NULL. Segunda corrida no hace nada.
 */
#[Signature('maintenances:backfill-notifications')]
#[Description('Dispara NotifyDueMaintenancesJob ahora mismo (one-shot post-deploy)')]
class MaintenancesBackfillNotifications extends Command
{
    public function handle(): int
    {
        $this->info('Disparando NotifyDueMaintenancesJob...');
        (new NotifyDueMaintenancesJob)->handle();
        $this->info('OK — los mantenimientos elegibles quedaron con notified_due_at=now(). Chequeá la campanita del agente asignado.');

        return self::SUCCESS;
    }
}
