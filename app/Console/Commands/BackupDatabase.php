<?php

namespace App\Console\Commands;

use App\Enums\BackupType;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

#[Signature('backups:database {--user= : ID del usuario que lo pidió (recibe aviso en la campanita)}')]
#[Description('Respaldo de la base de datos (diario): registra el resultado y avisa a los administradores')]
class BackupDatabase extends RunsBackupCommand
{
    protected function backupType(): BackupType
    {
        return BackupType::Database;
    }
}
