<?php

namespace App\Console\Commands;

use App\Enums\BackupType;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

#[Signature('backups:full {--user= : ID del usuario que lo pidió (recibe aviso en la campanita)}')]
#[Description('Respaldo completo (archivos + BD, semanal): envía el de BD y el link del completo a los administradores')]
class BackupFull extends RunsBackupCommand
{
    protected function backupType(): BackupType
    {
        return BackupType::Full;
    }
}
