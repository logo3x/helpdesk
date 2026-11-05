<?php

use App\Notifications\BackupNotifiable;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification;
use Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;
use Spatie\DbDumper\Compressors\GzipCompressor;

/*
|--------------------------------------------------------------------------
| Respaldos (spatie/laravel-backup)
|--------------------------------------------------------------------------
|
| Hay dos perfiles:
|
|  - BD (clave raíz de este archivo): solo el volcado de MySQL. Se ejecuta
|    a diario en la madrugada con `php artisan backups:database`.
|  - Completo (clave "full"): archivos subidos (storage/app, incluida la
|    media privada y pública) + BD. Se ejecuta los domingos con
|    `php artisan backups:full`. Spatie lo usa con `--config=backup.full`.
|
| Cada perfil guarda sus zips en su propia carpeta del disco de respaldos
| y tiene su propia retención, así la limpieza de uno no borra el otro.
|
| mysqldump: la ruta del binario sale de DB_DUMP_BINARY_PATH (ver
| config/database.php → connections.mysql.dump). En Windows usa barras
| normales, p. ej. "C:/Program Files/MySQL/MySQL Server 8.4/bin".
*/

$disk = env('BACKUP_DISK', 'backups');

$diskRoot = env('BACKUP_DISK_ROOT', storage_path('app/backups'));

$password = env('BACKUP_ARCHIVE_PASSWORD') ?: null;

$alertChannels = env('BACKUP_ALERTS_ENABLED', true) ? ['mail'] : [];

$notifications = [
    /*
     * Los correos de éxito y los de falla de backup:run los envía la app
     * (App\Notifications\BackupCompletedNotification y BackupFailedNotification),
     * con adjunto o link de descarga. Spatie solo avisa de limpiezas
     * fallidas y de respaldos "no saludables" detectados por backup:monitor.
     */
    'notifications' => [
        BackupHasFailedNotification::class => $alertChannels,
        UnhealthyBackupWasFoundNotification::class => $alertChannels,
        CleanupHasFailedNotification::class => $alertChannels,
        BackupWasSuccessfulNotification::class => [],
        HealthyBackupWasFoundNotification::class => [],
        CleanupWasSuccessfulNotification::class => [],
    ],

    /*
     * Destinatarios: usuarios con rol super_admin o admin, más los correos
     * de BACKUP_NOTIFY_EMAILS.
     */
    'notifiable' => BackupNotifiable::class,

    'mail' => [
        'to' => env('BACKUP_NOTIFICATION_EMAIL', env('MAIL_FROM_ADDRESS', 'your@example.com')),

        'from' => [
            'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
            'name' => env('MAIL_FROM_NAME', 'Example'),
        ],
    ],

    'slack' => [
        'webhook_url' => '',
        'channel' => null,
        'username' => null,
        'icon' => null,
    ],

    'discord' => [
        'webhook_url' => '',
        'username' => '',
        'avatar_url' => '',
    ],

    'webhook' => [
        'url' => '',
    ],
];

$databaseBackup = [
    /*
     * Nombre del respaldo = carpeta dentro del disco de respaldos.
     */
    'name' => env('BACKUP_DB_NAME', 'helpdesk-bd'),

    'source' => [
        'files' => [
            'include' => [],
            'exclude' => [],
            'follow_links' => false,
            'ignore_unreadable_directories' => false,
            'relative_path' => base_path(),
        ],

        'databases' => [
            env('DB_CONNECTION', 'mysql'),
        ],
    ],

    /*
     * gzip del volcado antes de meterlo al zip. Requiere el ejecutable
     * "gzip" en el PATH (en Windows no viene por defecto), por eso está
     * desactivado: el zip ya comprime el .sql con deflate nivel 9.
     */
    'database_dump_compressor' => env('BACKUP_DUMP_GZIP', false) ? GzipCompressor::class : null,

    'database_dump_file_timestamp_format' => null,

    'database_dump_filename_base' => 'database',

    'database_dump_file_extension' => '',

    'destination' => [
        'compression_method' => ZipArchive::CM_DEFAULT,

        'compression_level' => 9,

        /*
         * Prefijo del zip para distinguir el tipo a simple vista:
         * bd-2026-11-05-01-30-00.zip
         */
        'filename_prefix' => 'bd-',

        'disks' => [
            $disk,
        ],

        'continue_on_failure' => false,
    ],

    'temporary_directory' => storage_path('app/backup-temp'),

    /*
     * Contraseña del zip (AES-256). Si se define, el zip se abre con
     * 7-Zip o WinRAR: el Explorador de Windows no soporta AES.
     * Recomendada, porque el respaldo de BD se envía por correo.
     */
    'password' => $password,

    'encryption' => 'default',

    'verify_backup' => false,

    'tries' => 1,

    'retry_delay' => 0,
];

$fullBackup = array_replace_recursive($databaseBackup, [
    'name' => env('BACKUP_FULL_NAME', 'helpdesk-completo'),

    'source' => [
        'files' => [
            /*
             * Archivos subidos (media privada y pública, actas, evidencias,
             * adjuntos). El código está en git y vendor/node_modules/
             * public/build se regeneran, así que no se incluyen.
             *
             * El .env solo se incluye si BACKUP_INCLUDE_ENV=true. ADVERTENCIA:
             * contiene APP_KEY y credenciales; actívalo solo con
             * BACKUP_ARCHIVE_PASSWORD definido. Sin APP_KEY no se pueden
             * descifrar los secretos 2FA ni los datos cifrados al restaurar,
             * así que si no lo incluyes guarda la APP_KEY fuera de banda.
             */
            'include' => array_values(array_filter([
                storage_path('app'),
                env('BACKUP_INCLUDE_ENV', false) ? base_path('.env') : null,
            ])),

            'exclude' => [
                $diskRoot,
                storage_path('app/backups'),
                storage_path('app/backup-temp'),
                storage_path('framework'),
                storage_path('logs'),
                base_path('vendor'),
                base_path('node_modules'),
                public_path('build'),
            ],
        ],
    ],

    'destination' => [
        'filename_prefix' => 'completo-',
    ],
]);

return [

    'backup' => $databaseBackup,

    'notifications' => $notifications,

    'log_channel' => null,

    /*
     * Revisión diaria (backup:monitor): avisa a los administradores si el
     * último respaldo es muy viejo o si ocupan demasiado espacio.
     */
    'monitor_backups' => [
        [
            'name' => $databaseBackup['name'],
            'disks' => [$disk],
            'health_checks' => [
                MaximumAgeInDays::class => 1,
                MaximumStorageInMegabytes::class => (int) env('BACKUP_DB_MAX_MB', 5000),
            ],
        ],
        [
            'name' => $fullBackup['name'],
            'disks' => [$disk],
            'health_checks' => [
                MaximumAgeInDays::class => 8,
                MaximumStorageInMegabytes::class => (int) env('BACKUP_FULL_MAX_MB', 50000),
            ],
        ],
    ],

    /*
     * Retención del respaldo de BD: todos los de los últimos
     * BACKUP_DB_KEEP_DAYS días (7 por defecto) y nada más. Spatie nunca
     * borra el respaldo más reciente.
     */
    'cleanup' => [
        'strategy' => DefaultStrategy::class,

        'default_strategy' => [
            'keep_all_backups_for_days' => (int) env('BACKUP_DB_KEEP_DAYS', 7),
            'keep_daily_backups_for_days' => 0,
            'keep_weekly_backups_for_weeks' => 0,
            'keep_monthly_backups_for_months' => 0,
            'keep_yearly_backups_for_years' => 0,
            'delete_oldest_backups_when_using_more_megabytes_than' => (int) env('BACKUP_DB_MAX_MB', 5000),
        ],

        'tries' => 1,

        'retry_delay' => 0,
    ],

    /*
     * Perfil del respaldo completo semanal. Se usa con
     * `backup:run --config=backup.full` y `backup:clean --config=backup.full`.
     */
    'full' => [
        'backup' => $fullBackup,

        'notifications' => $notifications,

        'monitor_backups' => [
            [
                'name' => $fullBackup['name'],
                'disks' => [$disk],
                'health_checks' => [
                    MaximumAgeInDays::class => 8,
                    MaximumStorageInMegabytes::class => (int) env('BACKUP_FULL_MAX_MB', 50000),
                ],
            ],
        ],

        /*
         * Retención del completo: una copia por semana durante
         * BACKUP_FULL_KEEP_WEEKS semanas (6 por defecto).
         */
        'cleanup' => [
            'strategy' => DefaultStrategy::class,

            'default_strategy' => [
                'keep_all_backups_for_days' => 7,
                'keep_daily_backups_for_days' => 0,
                'keep_weekly_backups_for_weeks' => max(0, (int) env('BACKUP_FULL_KEEP_WEEKS', 6) - 1),
                'keep_monthly_backups_for_months' => 0,
                'keep_yearly_backups_for_years' => 0,
                'delete_oldest_backups_when_using_more_megabytes_than' => (int) env('BACKUP_FULL_MAX_MB', 50000),
            ],

            'tries' => 1,

            'retry_delay' => 0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ajustes propios del Helpdesk
    |--------------------------------------------------------------------------
    */
    'helpdesk' => [
        /*
         * Horarios (America/Bogota) en la franja de menor uso. Los usa
         * routes/console.php y la página "Respaldos" para mostrar la
         * próxima ejecución. full_day: 0 = domingo … 6 = sábado.
         */
        'schedule' => [
            'database_time' => env('BACKUP_DB_TIME', '01:30'),
            'full_day' => (int) env('BACKUP_FULL_DAY', 0),
            'full_time' => env('BACKUP_FULL_TIME', '02:30'),
            'cleanup_time' => env('BACKUP_CLEANUP_TIME', '04:00'),
            'full_cleanup_time' => env('BACKUP_FULL_CLEANUP_TIME', '04:15'),
            'monitor_time' => env('BACKUP_MONITOR_TIME', '05:00'),
        ],

        /*
         * Microsoft Graph acepta ~4 MB por correo y el adjunto viaja en
         * base64 dos veces (MIME + petición), así que por encima de este
         * tamaño el zip de BD no se adjunta y se envía un link.
         */
        'attachment_max_mb' => (float) env('BACKUP_MAIL_ATTACHMENT_MAX_MB', 2),

        /*
         * Vigencia del link de descarga enviado por correo. Además de la
         * firma, el link exige sesión iniciada con rol admin o super_admin.
         */
        'download_link_days' => (int) env('BACKUP_DOWNLOAD_LINK_DAYS', 7),

        /*
         * Correo del respaldo diario de BD:
         *  - always: confirmación diaria con el zip adjunto (si cabe).
         *  - on_failure: solo si falla (el semanal siempre se envía).
         */
        'daily_email' => env('BACKUP_DAILY_EMAIL', 'on_failure'),

        /*
         * Correos adicionales (separados por coma) que reciben los avisos.
         */
        'notify_emails' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('BACKUP_NOTIFY_EMAILS', ''))
        ))),

        /*
         * Tiempo máximo de un respaldo, en segundos.
         */
        'timeout' => (int) env('BACKUP_TIMEOUT', 3600),
    ],

];
