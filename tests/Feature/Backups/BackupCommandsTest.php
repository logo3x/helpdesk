<?php

use App\Enums\BackupType;
use App\Models\User;
use App\Notifications\BackupCompletedNotification;
use App\Notifications\BackupFailedNotification;
use App\Services\BackupManager;
use Database\Seeders\RoleSeeder;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

/**
 * Comandos backups:database y backups:full. El respaldo real (mysqldump
 * + zip) se simula: el BackupManager falso escribe un zip del tamaño
 * pedido en el disco "backups" falso, o devuelve un error.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('backups');
    Notification::fake();
    Cache::lock(BackupManager::LOCK_KEY)->forceRelease();

    config([
        'backup.helpdesk.daily_email' => 'on_failure',
        'backup.helpdesk.attachment_max_mb' => 1,
        'backup.helpdesk.notify_emails' => [],
    ]);

    $this->admin = User::factory()->create(['email' => 'admin@confipetrol.test']);
    $this->admin->assignRole('admin');
    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole('super_admin');
    $this->agent = User::factory()->create();
    $this->agent->assignRole('agente_soporte');
});

/**
 * @param  array<string, int>  $sizes  tamaño en bytes del zip por tipo ('bd', 'completo')
 */
function fakeBackupRunner(array $sizes = [], ?string $error = null): void
{
    app()->instance(BackupManager::class, new class($sizes, $error) extends BackupManager
    {
        /**
         * @param  array<string, int>  $sizes
         */
        public function __construct(private array $sizes, private ?string $error) {}

        protected function executeBackup(BackupType $type): array
        {
            if ($this->error !== null) {
                return [1, "Starting backup...\nBackup failed because: {$this->error}."];
            }

            $path = $type->backupName().'/'.$type->filenamePrefix().now()->format('Y-m-d-H-i-s').'.zip';
            $this->disk()->put($path, str_repeat('x', $this->sizes[$type->value] ?? 1024));

            return [0, 'Backup completed!'];
        }
    });
}

it('genera el respaldo de BD, lo registra y no envía correo si BACKUP_DAILY_EMAIL=on_failure', function () {
    fakeBackupRunner();

    $this->artisan('backups:database')->assertSuccessful();

    $latest = app(BackupManager::class)->latest(BackupType::Database);
    expect($latest)->not->toBeNull()
        ->and($latest['path'])->toStartWith('helpdesk-bd/bd-');

    $activity = Activity::where('log_name', BackupManager::LOG_NAME)->latest('id')->first();
    expect($activity->event)->toBe('succeeded')
        ->and($activity->getProperty('type'))->toBe('bd');

    Notification::assertNothingSent();
});

it('envía la confirmación diaria con el zip adjunto si BACKUP_DAILY_EMAIL=always', function () {
    config(['backup.helpdesk.daily_email' => 'always']);
    fakeBackupRunner(['bd' => 2048]);

    $this->artisan('backups:database')->assertSuccessful();

    Notification::assertSentTo([$this->admin, $this->superAdmin], BackupCompletedNotification::class,
        function (BackupCompletedNotification $notification, array $channels, User $notifiable): bool {
            $mail = $notification->toMail($notifiable);

            return $notification->type === BackupType::Database
                && $channels === ['mail']
                && count($mail->rawAttachments) === 1
                && $mail->rawAttachments[0]['name'] === basename($notification->path);
        });
    Notification::assertNotSentTo($this->agent, BackupCompletedNotification::class);
});

it('el semanal adjunta el zip de BD si pesa menos del umbral y envía link firmado del completo', function () {
    fakeBackupRunner(['bd' => 2048, 'completo' => 4096]);
    $this->artisan('backups:database')->assertSuccessful();

    $this->artisan('backups:full')->assertSuccessful();

    Notification::assertSentTo($this->admin, BackupCompletedNotification::class,
        function (BackupCompletedNotification $notification) {
            $mail = $notification->toMail($this->admin);

            return $notification->type === BackupType::Full
                && str_starts_with($notification->path, 'helpdesk-completo/completo-')
                && str_starts_with((string) $notification->databasePath, 'helpdesk-bd/bd-')
                && count($mail->rawAttachments) === 1
                && str_contains($mail->actionUrl, '/respaldos/descargar/helpdesk-completo/')
                && str_contains($mail->actionUrl, 'signature=')
                && str_contains($mail->actionUrl, 'expires=');
        });
});

it('no adjunta el zip de BD si supera el umbral y envía un link en su lugar', function () {
    fakeBackupRunner(['bd' => 2 * 1024 * 1024, 'completo' => 4096]);
    $this->artisan('backups:database')->assertSuccessful();

    $this->artisan('backups:full')->assertSuccessful();

    Notification::assertSentTo($this->admin, BackupCompletedNotification::class,
        function (BackupCompletedNotification $notification) {
            $mail = $notification->toMail($this->admin);
            $text = implode("\n", [...$mail->introLines, ...$mail->outroLines]);

            return $mail->rawAttachments === []
                && $mail->attachments === []
                && str_contains($text, 'no se adjuntó')
                && str_contains($text, '/respaldos/descargar/helpdesk-bd/');
        });
});

it('también avisa a los correos extra de BACKUP_NOTIFY_EMAILS', function () {
    config(['backup.helpdesk.notify_emails' => ['ti@confipetrol.test', 'admin@confipetrol.test']]);
    fakeBackupRunner();

    $this->artisan('backups:full')->assertSuccessful();

    Notification::assertSentOnDemand(BackupCompletedNotification::class,
        fn ($notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'ti@confipetrol.test');
    Notification::assertSentOnDemandTimes(BackupCompletedNotification::class, 1);
});

it('si el respaldo falla registra el error y alerta a los administradores', function () {
    fakeBackupRunner(error: 'mysqldump no encontrado');

    $this->artisan('backups:database')->assertFailed();

    $activity = Activity::where('log_name', BackupManager::LOG_NAME)->latest('id')->first();
    expect($activity->event)->toBe('failed')
        ->and($activity->getProperty('error'))->toContain('mysqldump no encontrado');

    Notification::assertSentTo([$this->admin, $this->superAdmin], BackupFailedNotification::class,
        fn (BackupFailedNotification $notification): bool => str_contains($notification->error, 'mysqldump no encontrado'));
    Notification::assertNotSentTo($this->agent, BackupFailedNotification::class);
});

it('no corre dos respaldos a la vez', function () {
    fakeBackupRunner();
    $lock = Cache::lock(BackupManager::LOCK_KEY, 60);
    $lock->get();

    $this->artisan('backups:full')->assertFailed();

    Notification::assertSentTo($this->admin, BackupFailedNotification::class,
        fn (BackupFailedNotification $notification): bool => str_contains($notification->error, 'otro respaldo en curso'));

    $lock->release();
});
