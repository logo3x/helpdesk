<?php

namespace App\Jobs;

use App\Models\SlaConfig;
use App\Models\User;
use App\Notifications\SlaConfigChangedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Notifica a TODOS los usuarios sobre un cambio en un SlaConfig.
 *
 * Se dispara desde SlaConfigObserver que ya aplicó la ventana de 15
 * min por SlaConfig. Este job agrega otro guardarraíl a nivel usuario:
 * cache de 30 min por (user + sla) para evitar que un mismo usuario
 * reciba múltiples notificaciones del mismo SLA en poco tiempo aunque
 * cambien SlaConfigs distintas.
 */
class DispatchSlaChangeNotificationsJob implements ShouldQueue
{
    use Queueable;

    /** Ventana antispam por usuario, en segundos. */
    protected const PER_USER_WINDOW_SECONDS = 1800; // 30 min

    /**
     * @param  array<int, string>  $changedFields
     */
    public function __construct(
        public int $slaConfigId,
        public array $changedFields,
    ) {}

    public function handle(): void
    {
        $sla = SlaConfig::query()->with('department')->find($this->slaConfigId);
        if (! $sla) {
            return;
        }

        $notified = 0;
        $skippedByCache = 0;

        User::query()
            ->whereHas('roles')
            ->chunkById(200, function ($users) use ($sla, &$notified, &$skippedByCache): void {
                foreach ($users as $user) {
                    $userLockKey = "sla-notif-user:{$user->id}:{$sla->id}";
                    if (! Cache::add($userLockKey, true, self::PER_USER_WINDOW_SECONDS)) {
                        $skippedByCache++;

                        continue;
                    }

                    $user->notify(new SlaConfigChangedNotification($sla, $this->changedFields));
                    $notified++;
                }
            });

        Log::info('[SLA-NOTIF] Notificación masiva por cambio de SLA', [
            'sla_id' => $sla->id,
            'priority' => $sla->priority?->value,
            'department' => $sla->department?->name,
            'changed_fields' => $this->changedFields,
            'notified' => $notified,
            'skipped_by_antispam' => $skippedByCache,
        ]);
    }
}
