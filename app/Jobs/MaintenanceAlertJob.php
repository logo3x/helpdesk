<?php

namespace App\Jobs;

use App\Models\Asset;
use App\Models\User;
use App\Notifications\AssetMaintenanceDueNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Job semanal de alertas de mantenimiento de activos.
 *
 * Hace dos cosas en una sola ejecución (lunes a las 7am):
 *
 * 1. CAMPANITA (database) — activos que vencen en ≤7 días:
 *    Notifica al responsable de mantenimiento del activo y al
 *    supervisor_soporte + admin sobre cada equipo inminente.
 *
 * 2. CORREO SEMANAL — activos que vencen en ≤14 días:
 *    Envía a supervisores y admins un resumen consolidado con todos
 *    los activos del período, para que puedan planificar las visitas.
 */
class MaintenanceAlertJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $now = now();

        // ── 1. Campanita: activos que vencen en los próximos 7 días ────────
        $imminentAssets = Asset::query()
            ->whereNotNull('next_maintenance_at')
            ->whereBetween('next_maintenance_at', [$now->startOfDay(), $now->copy()->addDays(7)->endOfDay()])
            ->where('status', '!=', 'retired')
            ->with(['user', 'maintenanceResponsible'])
            ->get();

        foreach ($imminentAssets as $asset) {
            $recipients = collect();

            // Responsable directo del mantenimiento
            if ($asset->maintenanceResponsible) {
                $recipients->push($asset->maintenanceResponsible);
            }

            // Supervisores y admins
            $staff = User::role(['supervisor_soporte', 'admin', 'super_admin'])->get();
            $recipients = $recipients->merge($staff)->unique('id');

            foreach ($recipients as $user) {
                try {
                    $user->notify(new AssetMaintenanceDueNotification(asset: $asset));
                } catch (\Throwable $e) {
                    Log::warning("MaintenanceAlertJob: no se pudo notificar a user#{$user->id} sobre asset#{$asset->id}: {$e->getMessage()}");
                }
            }
        }

        // ── 2. Correo semanal: activos que vencen en los próximos 14 días ──
        $upcomingAssets = Asset::query()
            ->whereNotNull('next_maintenance_at')
            ->whereBetween('next_maintenance_at', [$now->copy()->startOfDay(), $now->copy()->addDays(14)->endOfDay()])
            ->where('status', '!=', 'retired')
            ->with(['user', 'maintenanceResponsible'])
            ->orderBy('next_maintenance_at')
            ->get();

        if ($upcomingAssets->isEmpty()) {
            return;
        }

        $emailRecipients = User::role(['supervisor_soporte', 'admin', 'super_admin'])->get();

        foreach ($emailRecipients as $user) {
            try {
                $user->notify(new AssetMaintenanceDueNotification(assetsForMail: $upcomingAssets));
            } catch (\Throwable $e) {
                Log::warning("MaintenanceAlertJob: no se pudo enviar correo a user#{$user->id}: {$e->getMessage()}");
            }
        }
    }
}
