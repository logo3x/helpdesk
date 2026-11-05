<?php

namespace App\Enums;

use Carbon\CarbonInterface;
use Cron\CronExpression;
use Illuminate\Support\Facades\Date;

/**
 * Tipos de respaldo del Helpdesk (ver config/backup.php).
 *
 * - Database: volcado de MySQL, diario en la madrugada.
 * - Full: archivos subidos + BD, semanal.
 */
enum BackupType: string
{
    case Database = 'bd';
    case Full = 'completo';

    public function label(): string
    {
        return match ($this) {
            self::Database => 'Base de datos',
            self::Full => 'Completo',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Database => 'info',
            self::Full => 'success',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Database => 'heroicon-o-circle-stack',
            self::Full => 'heroicon-o-archive-box',
        };
    }

    /**
     * Clave de configuración que se pasa a `backup:run --config=`.
     */
    public function configKey(): string
    {
        return match ($this) {
            self::Database => 'backup',
            self::Full => 'backup.full',
        };
    }

    /**
     * Carpeta del disco de respaldos donde quedan los zips de este tipo.
     */
    public function backupName(): string
    {
        return (string) config($this->configKey().'.backup.name');
    }

    public function filenamePrefix(): string
    {
        return (string) config($this->configKey().'.backup.destination.filename_prefix');
    }

    public function command(): string
    {
        return match ($this) {
            self::Database => 'backups:database',
            self::Full => 'backups:full',
        };
    }

    /**
     * Días máximos sin respaldo antes de considerarlo "no sano".
     */
    public function maxAgeInDays(): int
    {
        return match ($this) {
            self::Database => 1,
            self::Full => 8,
        };
    }

    /**
     * Expresión cron de la ejecución automática, según
     * config('backup.helpdesk.schedule').
     */
    public function cronExpression(): string
    {
        $schedule = config('backup.helpdesk.schedule');

        [$hour, $minute] = array_map('intval', explode(':', match ($this) {
            self::Database => $schedule['database_time'],
            self::Full => $schedule['full_time'],
        }));

        return match ($this) {
            self::Database => "{$minute} {$hour} * * *",
            self::Full => "{$minute} {$hour} * * {$schedule['full_day']}",
        };
    }

    public function nextRunDate(): CarbonInterface
    {
        $timezone = config('app.timezone');

        return Date::instance(
            (new CronExpression($this->cronExpression()))->getNextRunDate(now($timezone), 0, false, $timezone)
        );
    }

    public function scheduleDescription(): string
    {
        $schedule = config('backup.helpdesk.schedule');

        return match ($this) {
            self::Database => "Todos los días a las {$schedule['database_time']}",
            self::Full => 'Cada '.self::dayName($schedule['full_day'])." a las {$schedule['full_time']}",
        };
    }

    public static function dayName(int $day): string
    {
        return ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'][$day] ?? 'domingo';
    }
}
