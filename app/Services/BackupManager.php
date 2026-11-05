<?php

namespace App\Services;

use App\Enums\BackupType;
use App\Models\User;
use App\Notifications\BackupCompletedNotification;
use App\Notifications\BackupFailedNotification;
use Carbon\CarbonInterface;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Throwable;

/**
 * Orquesta los respaldos del Helpdesk sobre spatie/laravel-backup.
 *
 * - Ejecuta el perfil (BD o completo) con `backup:run`.
 * - Registra el resultado en la bitácora (log "respaldos").
 * - Avisa por correo a admin/super_admin: confirmación con el zip de BD
 *   adjunto (si cabe en Graph) y link firmado del completo, o alerta si
 *   falló.
 * - Lista, resuelve (sin path traversal) y borra los zips del disco.
 */
class BackupManager
{
    public const LOG_NAME = 'respaldos';

    public const LOCK_KEY = 'backups:running';

    /** @var array<int, string> */
    public const ADMIN_ROLES = ['super_admin', 'admin'];

    public function diskName(): string
    {
        return (string) config('backup.backup.destination.disks.0', 'backups');
    }

    public function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    /**
     * Respaldos existentes en el disco, del más nuevo al más viejo.
     * Los zips de carpetas que no son de un perfil actual (respaldos
     * anteriores a la separación BD/Completo) salen con type = null.
     *
     * @return Collection<int, array{key: string, path: string, folder: string, file: string, type: ?BackupType, date: CarbonInterface, size: int}>
     */
    public function backups(?BackupType $type = null): Collection
    {
        $disk = $this->disk();

        try {
            $files = $disk->allFiles();
        } catch (Throwable) {
            return collect();
        }

        return collect($files)
            ->filter(fn (string $path): bool => Str::endsWith(Str::lower($path), '.zip') && str_contains($path, '/'))
            ->map(function (string $path) use ($disk): array {
                $folder = Str::beforeLast($path, '/');
                $file = Str::afterLast($path, '/');

                return [
                    'key' => md5($path),
                    'path' => $path,
                    'folder' => $folder,
                    'file' => $file,
                    'type' => $this->typeFor($folder, $file),
                    'date' => Date::createFromTimestamp($disk->lastModified($path), config('app.timezone')),
                    'size' => (int) $disk->size($path),
                ];
            })
            ->when($type !== null, fn (Collection $backups): Collection => $backups->filter(fn (array $backup): bool => $backup['type'] === $type))
            ->sortByDesc(fn (array $backup): int => $backup['date']->getTimestamp())
            ->values();
    }

    /**
     * @return array{key: string, path: string, folder: string, file: string, type: ?BackupType, date: CarbonInterface, size: int}|null
     */
    public function latest(BackupType $type): ?array
    {
        return $this->backups($type)->first();
    }

    public function totalSize(): int
    {
        return (int) $this->backups()->sum('size');
    }

    /**
     * Espacio libre en la unidad del disco de respaldos (solo discos locales).
     */
    public function freeSpace(): ?int
    {
        $root = config('filesystems.disks.'.$this->diskName().'.root');

        if (! $root || ! is_dir($root)) {
            return null;
        }

        $free = @disk_free_space($root);

        return $free === false ? null : (int) $free;
    }

    /**
     * Sano = hay un respaldo más reciente que el máximo permitido y la
     * última ejecución registrada no falló.
     */
    public function isHealthy(BackupType $type): bool
    {
        $latest = $this->latest($type);

        if (! $latest || $latest['date']->lt(now()->subDays($type->maxAgeInDays())->subHours(2))) {
            return false;
        }

        $lastRun = $this->lastRun($type);

        return ! $lastRun || $lastRun->event !== 'failed';
    }

    public function lastRun(BackupType $type): ?Activity
    {
        return Activity::query()
            ->where('log_name', self::LOG_NAME)
            ->whereIn('event', ['succeeded', 'failed'])
            ->where('properties->type', $type->value)
            ->latest('id')
            ->first();
    }

    public function isRunning(): bool
    {
        return Cache::has(self::LOCK_KEY.':started');
    }

    /**
     * Devuelve la ruta relativa del zip solo si la carpeta y el archivo
     * existen tal cual en el disco de respaldos. Así un nombre con "..",
     * barras o rutas absolutas nunca llega al sistema de archivos.
     */
    public function resolvePath(string $folder, string $file): ?string
    {
        foreach ([$folder, $file] as $segment) {
            if ($segment === '' || str_contains($segment, '..') || preg_match('#[/\\\\:\x00]#', $segment)) {
                return null;
            }
        }

        if (! Str::endsWith(Str::lower($file), '.zip')) {
            return null;
        }

        $disk = $this->disk();

        try {
            if (! in_array($folder, $disk->directories(), true)) {
                return null;
            }

            $path = $folder.'/'.$file;

            return in_array($path, $disk->files($folder), true) ? $path : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Link firmado y temporal de descarga. La ruta además exige sesión
     * con rol admin o super_admin.
     */
    public function downloadUrl(string $path, ?CarbonInterface $expiresAt = null): string
    {
        return URL::temporarySignedRoute(
            'backups.download',
            $expiresAt ?? now()->addDays(config('backup.helpdesk.download_link_days', 7)),
            [
                'folder' => Str::beforeLast($path, '/'),
                'file' => Str::afterLast($path, '/'),
            ],
        );
    }

    public function attachmentLimitInBytes(): int
    {
        return (int) round((float) config('backup.helpdesk.attachment_max_mb', 2) * 1024 * 1024);
    }

    public function canAttach(int $sizeInBytes): bool
    {
        return $sizeInBytes > 0 && $sizeInBytes <= $this->attachmentLimitInBytes();
    }

    public function delete(string $path, ?User $user = null): bool
    {
        $deleted = $this->disk()->delete($path);

        if ($deleted) {
            activity(self::LOG_NAME)
                ->causedBy($user)
                ->event('deleted')
                ->withProperties(['path' => $path])
                ->log('Respaldo eliminado: '.$path);
        }

        return $deleted;
    }

    /**
     * Ejecuta el respaldo, lo registra y avisa por correo.
     *
     * @return array{successful: bool, type: BackupType, path: ?string, size: int, duration: int, error: ?string}
     */
    public function run(BackupType $type, ?User $requestedBy = null): array
    {
        $lock = Cache::lock(self::LOCK_KEY, (int) config('backup.helpdesk.timeout', 3600) + 600);

        if (! $lock->get()) {
            $result = $this->result($type, false, error: 'Ya hay otro respaldo en curso. Espera a que termine e inténtalo de nuevo.');
            $this->report($result, $requestedBy);

            return $result;
        }

        $startedAt = now();
        Cache::put(self::LOCK_KEY.':started', ['type' => $type->value, 'at' => $startedAt->toIso8601String()], 4 * 3600);

        try {
            [$exitCode, $output] = $this->executeBackup($type);

            $newest = $exitCode === 0 ? $this->latest($type) : null;

            if ($newest && $newest['date']->getTimestamp() >= $startedAt->getTimestamp() - 5) {
                $result = $this->result($type, true, $newest['path'], $newest['size'], $startedAt);
            } else {
                $result = $this->result($type, false, startedAt: $startedAt, error: $this->extractError($output)
                    ?? 'El respaldo terminó sin generar ningún archivo. Revisa storage/logs/laravel.log.');
            }
        } catch (Throwable $exception) {
            report($exception);

            $result = $this->result($type, false, startedAt: $startedAt, error: $exception->getMessage());
        } finally {
            Cache::forget(self::LOCK_KEY.':started');
            $lock->release();
        }

        $this->report($result, $requestedBy);

        return $result;
    }

    /**
     * Llama a spatie. Aislado en un método para poder simularlo en tests.
     *
     * @return array{0: int, 1: string}
     */
    protected function executeBackup(BackupType $type): array
    {
        $parameters = [
            '--config' => $type->configKey(),
            '--disable-notifications' => true,
            '--timeout' => (int) config('backup.helpdesk.timeout', 3600),
        ];

        if ($type === BackupType::Database) {
            $parameters['--only-db'] = true;
        }

        $exitCode = Artisan::call('backup:run', $parameters);

        return [$exitCode, Artisan::output()];
    }

    /**
     * Advertencia si mysqldump no está donde dice DB_DUMP_BINARY_PATH.
     */
    public function dumpBinaryWarning(): ?string
    {
        $connection = config('database.default');

        if (! in_array(config("database.connections.{$connection}.driver"), ['mysql', 'mariadb'], true)) {
            return null;
        }

        $binaryPath = config("database.connections.{$connection}.dump.dump_binary_path");

        if (blank($binaryPath)) {
            return PHP_OS_FAMILY === 'Windows'
                ? 'DB_DUMP_BINARY_PATH no está definido: en Windows mysqldump suele no estar en el PATH del servicio y el respaldo de BD fallará.'
                : null;
        }

        $binary = rtrim($binaryPath, '/\\').DIRECTORY_SEPARATOR.(PHP_OS_FAMILY === 'Windows' ? 'mysqldump.exe' : 'mysqldump');

        return is_file($binary) ? null : "No se encontró mysqldump en {$binaryPath}. Revisa DB_DUMP_BINARY_PATH.";
    }

    /**
     * Usuarios que reciben los avisos de respaldo.
     *
     * @return Collection<int, User>
     */
    public function recipients(): Collection
    {
        return User::role(self::ADMIN_ROLES)
            ->whereNotNull('email')
            ->get();
    }

    /**
     * Correos adicionales de BACKUP_NOTIFY_EMAILS que no son de un admin.
     *
     * @return array<int, string>
     */
    public function extraEmails(): array
    {
        $adminEmails = $this->recipients()->pluck('email')->map(fn (string $email): string => Str::lower($email));

        return collect(config('backup.helpdesk.notify_emails', []))
            ->filter(fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->reject(fn (string $email): bool => $adminEmails->contains(Str::lower($email)))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array{successful: bool, type: BackupType, path: ?string, size: int, duration: int, error: ?string}  $result
     */
    protected function report(array $result, ?User $requestedBy): void
    {
        $type = $result['type'];

        activity(self::LOG_NAME)
            ->causedBy($requestedBy)
            ->event($result['successful'] ? 'succeeded' : 'failed')
            ->withProperties([
                'type' => $type->value,
                'path' => $result['path'],
                'size' => $result['size'],
                'duration' => $result['duration'],
                'error' => $result['error'],
                'origin' => $requestedBy ? 'manual' : 'automatico',
            ])
            ->log($result['successful']
                ? "Respaldo {$type->label()} generado"
                : "Respaldo {$type->label()} falló");

        if ($result['successful']) {
            Log::info("Respaldo {$type->value} generado", ['path' => $result['path'], 'size' => $result['size']]);
        } else {
            Log::error("Respaldo {$type->value} falló", ['error' => $result['error']]);
        }

        $this->notifyAdmins($result);
        $this->notifyRequester($result, $requestedBy);
    }

    /**
     * @param  array{successful: bool, type: BackupType, path: ?string, size: int, duration: int, error: ?string}  $result
     */
    protected function notifyAdmins(array $result): void
    {
        $type = $result['type'];

        if (! $result['successful']) {
            $notification = new BackupFailedNotification($type, (string) $result['error']);
        } else {
            if ($type === BackupType::Database && config('backup.helpdesk.daily_email') !== 'always') {
                return;
            }

            $database = $type === BackupType::Database
                ? ['path' => $result['path'], 'size' => $result['size']]
                : $this->latest(BackupType::Database);

            $notification = new BackupCompletedNotification(
                type: $type,
                path: (string) $result['path'],
                size: $result['size'],
                databasePath: $database['path'] ?? null,
                databaseSize: (int) ($database['size'] ?? 0),
            );
        }

        Notification::send($this->recipients(), $notification);

        foreach ($this->extraEmails() as $email) {
            Notification::route('mail', $email)->notify($notification);
        }
    }

    /**
     * Campanita de Filament para quien pidió el respaldo desde la página.
     *
     * @param  array{successful: bool, type: BackupType, path: ?string, size: int, duration: int, error: ?string}  $result
     */
    protected function notifyRequester(array $result, ?User $requestedBy): void
    {
        if (! $requestedBy) {
            return;
        }

        $notification = FilamentNotification::make()
            ->icon($result['type']->icon());

        if ($result['successful']) {
            $notification
                ->title("Respaldo {$result['type']->label()} listo")
                ->body('Tamaño: '.Number::fileSize($result['size'], precision: 1).'. Ya puedes descargarlo en Configuración → Respaldos.')
                ->success();
        } else {
            $notification
                ->title("El respaldo {$result['type']->label()} falló")
                ->body(Str::limit((string) $result['error'], 300))
                ->danger();
        }

        $notification->sendToDatabase($requestedBy);
    }

    /**
     * @return array{successful: bool, type: BackupType, path: ?string, size: int, duration: int, error: ?string}
     */
    protected function result(
        BackupType $type,
        bool $successful,
        ?string $path = null,
        int $size = 0,
        ?CarbonInterface $startedAt = null,
        ?string $error = null,
    ): array {
        return [
            'successful' => $successful,
            'type' => $type,
            'path' => $path,
            'size' => $size,
            'duration' => $startedAt ? (int) $startedAt->diffInSeconds(now(), true) : 0,
            'error' => $error,
        ];
    }

    /**
     * Toma el mensaje útil de la salida de spatie ("Backup failed because: …").
     */
    protected function extractError(string $output): ?string
    {
        if (preg_match('/Backup failed because:?\s*(.+)/s', $output, $matches)) {
            return Str::limit(trim($matches[1]), 2000);
        }

        $output = trim($output);

        return $output === '' ? null : Str::limit($output, 2000);
    }

    protected function typeFor(string $folder, string $file): ?BackupType
    {
        foreach (BackupType::cases() as $type) {
            if ($folder === $type->backupName()) {
                return $type;
            }
        }

        foreach (BackupType::cases() as $type) {
            if ($type->filenamePrefix() !== '' && Str::startsWith($file, $type->filenamePrefix())) {
                return $type;
            }
        }

        return null;
    }
}
