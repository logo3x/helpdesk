<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\KactusSyncFailedNotification;
use App\Services\KactusService;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('kactus:sync
    {--since= : ISO8601 timestamp; trae solo empleados modificados desde esa fecha}
    {--user= : ID en Kactus; sincroniza solo ese empleado}
    {--dry-run : Loguea lo que haría sin tocar la BD}')]
#[Description('Sincroniza empleados desde Kactus a la tabla users local')]
class KactusSync extends Command
{
    public function handle(KactusService $kactus): int
    {
        if (! config('kactus.enabled')) {
            $this->warn('Kactus está deshabilitado (KACTUS_ENABLED=false). Nada que sincronizar.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info('Modo DRY-RUN: no se persistirán cambios.');
        }

        // Sync individual.
        if ($kactusId = $this->option('user')) {
            return $this->syncSingle($kactus, $kactusId);
        }

        // Sync masivo.
        $since = $this->option('since') ? Carbon::parse($this->option('since')) : null;
        $this->info('Trayendo empleados desde Kactus'.($since ? " modificados desde {$since->toIso8601String()}" : ' (todos)').'…');

        try {
            if ($this->option('dry-run')) {
                $count = 0;
                foreach ($kactus->fetchAllSince($since) as $emp) {
                    $this->line("  · {$emp->kactusId} {$emp->name} [{$emp->status}]");
                    $count++;
                }
                $this->info("DRY-RUN: {$count} empleados habrían sido sincronizados.");

                return self::SUCCESS;
            }

            $result = $kactus->syncBatch($kactus->fetchAllSince($since));
        } catch (\Throwable $e) {
            $this->error('Error fatal: '.$e->getMessage());
            $this->notifySuperAdmin($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Sync completado: %d creados, %d actualizados, %d desactivados, %d errores.',
            $result->created,
            $result->updated,
            $result->deactivated,
            count($result->errors),
        ));

        if ($result->hasErrors()) {
            foreach ($result->errors as $err) {
                $this->warn("  · {$err}");
            }
            $this->notifySuperAdmin('Errores parciales en sync: '.implode(' | ', $result->errors));
        }

        return self::SUCCESS;
    }

    protected function syncSingle(KactusService $kactus, string $kactusId): int
    {
        $emp = $kactus->fetchEmployee($kactusId);

        if (! $emp) {
            $this->error("Empleado {$kactusId} no encontrado en Kactus (o error de API).");

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info("DRY-RUN: se sincronizaría a {$emp->name} ({$emp->identification})");

            return self::SUCCESS;
        }

        $user = $kactus->syncToUser($emp);
        $this->info("OK: {$user->name} (#{$user->id}) sincronizado.");

        return self::SUCCESS;
    }

    protected function notifySuperAdmin(string $reason): void
    {
        User::role('super_admin')->each(
            fn (User $u) => $u->notify(new KactusSyncFailedNotification($reason))
        );
    }
}
