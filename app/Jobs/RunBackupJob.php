<?php

namespace App\Jobs;

use App\Enums\BackupType;
use App\Models\User;
use App\Services\BackupManager;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Respaldo pedido desde la página "Respaldos" del panel admin. Corre en
 * la cola (worker) para no depender del timeout de IIS.
 */
class RunBackupJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout;

    public int $uniqueFor = 7200;

    public function __construct(
        public BackupType $type,
        public ?int $userId = null,
    ) {
        $this->timeout = (int) config('backup.helpdesk.timeout', 3600) + 300;
    }

    public function uniqueId(): string
    {
        return $this->type->value;
    }

    public function handle(BackupManager $backups): void
    {
        set_time_limit(0);

        $backups->run($this->type, $this->userId ? User::find($this->userId) : null);
    }
}
