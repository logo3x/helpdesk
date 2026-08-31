<?php

namespace App\Observers;

use App\Jobs\DispatchSlaChangeNotificationsJob;
use App\Models\SlaConfig;
use Illuminate\Support\Facades\Cache;

/**
 * Observador del modelo SlaConfig — Fase 7 Sprint 7 (2026-08-25).
 *
 * Cuando un supervisor/admin modifica los umbrales de un SLA
 * (first_response_minutes / resolution_minutes / is_active),
 * dispara una notificación masiva a TODOS los usuarios activos.
 *
 * Control de spam:
 *  - Ventana de 15 min por SlaConfig — múltiples updates en la
 *    misma ventana solo generan UNA notificación consolidada.
 *  - El job en background además evita duplicados a nivel usuario
 *    con cache de 30 min (mismo user + mismo sla → una sola vez).
 */
class SlaConfigObserver
{
    /**
     * Ventana de agrupación en segundos. Cambios que ocurran en
     * esta ventana comparten la misma notificación.
     */
    protected const SPAM_WINDOW_SECONDS = 900; // 15 min

    /**
     * Campos cuyo cambio dispara la notificación. Cambios en
     * otros campos (created_at, updated_at, department_id, priority)
     * NO disparan.
     */
    protected const WATCHED_FIELDS = [
        'first_response_minutes',
        'resolution_minutes',
        'is_active',
    ];

    public function updated(SlaConfig $sla): void
    {
        $touched = collect(self::WATCHED_FIELDS)
            ->filter(fn (string $field) => $sla->wasChanged($field))
            ->all();

        if ($touched === []) {
            return;
        }

        // Antispam: si ya notifiqué este SlaConfig en los últimos
        // 15 min, no vuelvo a hacerlo. El add() es atómico —
        // devuelve true solo la primera vez.
        $lockKey = "sla-notif-lock:{$sla->id}";
        if (! Cache::add($lockKey, true, self::SPAM_WINDOW_SECONDS)) {
            return;
        }

        DispatchSlaChangeNotificationsJob::dispatch(
            slaConfigId: $sla->id,
            changedFields: $touched,
        );
    }

    public function created(SlaConfig $sla): void
    {
        // La creación de un SLA nuevo también notifica.
        $lockKey = "sla-notif-lock:{$sla->id}";
        if (! Cache::add($lockKey, true, self::SPAM_WINDOW_SECONDS)) {
            return;
        }

        DispatchSlaChangeNotificationsJob::dispatch(
            slaConfigId: $sla->id,
            changedFields: ['created'],
        );
    }
}
