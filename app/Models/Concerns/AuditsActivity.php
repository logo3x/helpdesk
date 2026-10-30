<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Registra en la bitácora (activity_log) quién crea, edita o elimina
 * registros del modelo, guardando solo los campos que cambiaron.
 *
 * Los modelos definen `$auditLogName` (módulo que se ve en la bitácora)
 * y opcionalmente `$auditExcept` con campos que nunca deben guardarse
 * y `$auditOnlyUserActions` para ignorar cambios automáticos.
 */
trait AuditsActivity
{
    use LogsActivity {
        shouldLogEvent as baseShouldLogEvent;
    }

    /**
     * Con `$auditOnlyUserActions = true` no se registran los cambios
     * automáticos (agente de inventario, tareas programadas), solo los
     * que hace una persona con sesión iniciada.
     */
    protected function shouldLogEvent(string $eventName): bool
    {
        if (($this->auditOnlyUserActions ?? false) && ! auth()->check()) {
            return false;
        }

        return $this->baseShouldLogEvent($eventName);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logExcept(array_merge(
                ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'last_login_at', 'last_login_ip', 'updated_at'],
                $this->auditExcept ?? [],
            ))
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName($this->auditLogName ?? 'general');
    }
}
