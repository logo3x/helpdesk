<?php

namespace App\Console\Commands;

use App\Enums\BackupType;
use App\Models\User;
use App\Services\BackupManager;
use Illuminate\Console\Command;
use Illuminate\Support\Number;

/**
 * Base de los comandos backups:database y backups:full: ejecuta el
 * perfil con BackupManager (que registra el resultado y avisa por
 * correo) y muestra el resumen en consola.
 */
abstract class RunsBackupCommand extends Command
{
    abstract protected function backupType(): BackupType;

    public function handle(BackupManager $backups): int
    {
        $type = $this->backupType();
        $requestedBy = $this->option('user') ? User::find($this->option('user')) : null;

        $this->info("Generando respaldo {$type->label()}…");

        $result = $backups->run($type, $requestedBy);

        if (! $result['successful']) {
            $this->error("El respaldo {$type->label()} falló: {$result['error']}");

            return self::FAILURE;
        }

        $this->info("Respaldo listo: {$result['path']} (".Number::fileSize($result['size'], precision: 1).", {$result['duration']} s).");

        return self::SUCCESS;
    }
}
