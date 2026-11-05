<?php

use App\Enums\BackupType;
use App\Filament\Pages\Backups;
use App\Jobs\RunBackupJob;
use App\Models\User;
use App\Services\BackupManager;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShieldPermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Página "Respaldos" del panel admin.
 */
beforeEach(function () {
    $this->seed([RoleSeeder::class, ShieldPermissionSeeder::class]);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Storage::fake('backups');

    Storage::disk('backups')->put('helpdesk-bd/bd-2026-11-05-01-30-00.zip', str_repeat('x', 2048));
    Storage::disk('backups')->put('helpdesk-completo/completo-2026-11-01-02-30-00.zip', str_repeat('x', 4096));
    Storage::disk('backups')->put('Helpdesk Confipetrol/2026-07-30-10-00-00.zip', 'x');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);
});

it('muestra estado, horarios y la tabla de respaldos con su tipo', function () {
    $records = app(BackupManager::class)->backups()->keyBy('key');

    Livewire::test(Backups::class)
        ->assertOk()
        ->assertSee('Base de datos (diario)')
        ->assertSee('Completo (semanal)')
        ->assertSee('Todos los días a las 01:30')
        ->assertSee('Cada domingo a las 02:30')
        ->assertSee('Espacio usado')
        ->assertCanSeeTableRecords($records->keys()->all())
        ->assertSee('bd-2026-11-05-01-30-00.zip')
        ->assertSee('completo-2026-11-01-02-30-00.zip')
        ->assertSee('Anterior');
});

it('filtra la tabla por tipo', function () {
    $backups = app(BackupManager::class);
    $database = $backups->latest(BackupType::Database);
    $full = $backups->latest(BackupType::Full);

    Livewire::test(Backups::class)
        ->filterTable('type', 'bd')
        ->assertCanSeeTableRecords([$database['key']])
        ->assertCanNotSeeTableRecords([$full['key']]);
});

it('el botón Descargar usa el link firmado temporal', function () {
    $this->freezeTime();
    $database = app(BackupManager::class)->latest(BackupType::Database);
    $expectedUrl = app(BackupManager::class)->downloadUrl($database['path'], now()->addMinutes(30));

    expect($expectedUrl)->toContain('/respaldos/descargar/helpdesk-bd/bd-2026-11-05-01-30-00.zip')
        ->toContain('signature=');

    Livewire::test(Backups::class)
        ->assertActionHasUrl(TestAction::make('download')->table($database['key']), $expectedUrl);
});

it('elimina un respaldo con confirmación', function () {
    $full = app(BackupManager::class)->latest(BackupType::Full);

    Livewire::test(Backups::class)
        ->callAction(TestAction::make('delete')->table($full['key']))
        ->assertNotified('Respaldo eliminado');

    Storage::disk('backups')->assertMissing('helpdesk-completo/completo-2026-11-01-02-30-00.zip');
});

it('los botones de respaldo inmediato despachan el job a la cola', function () {
    config(['queue.default' => 'database']);
    Queue::fake();

    Livewire::test(Backups::class)
        ->callAction('backupDatabaseNow')
        ->assertNotified('Respaldo en marcha')
        ->callAction('backupFullNow');

    Queue::assertPushed(RunBackupJob::class, fn (RunBackupJob $job): bool => $job->type === BackupType::Database && $job->userId === $this->admin->id);
    Queue::assertPushed(RunBackupJob::class, fn (RunBackupJob $job): bool => $job->type === BackupType::Full);
});

it('solo admin y super_admin pueden entrar', function (string $role, bool $allowed) {
    $user = User::factory()->create();
    $user->assignRole($role);
    $this->actingAs($user);

    expect(Backups::canAccess())->toBe($allowed);
})->with([
    ['super_admin', true],
    ['admin', true],
    ['supervisor_soporte', false],
    ['agente_soporte', false],
    ['usuario_final', false],
]);

it('la ruta de la página responde 200 para admin y 403 para otros roles', function () {
    $this->get(Backups::getUrl(panel: 'admin'))->assertOk();

    $agent = User::factory()->create();
    $agent->assignRole('agente_soporte');

    $this->actingAs($agent)->get(Backups::getUrl(panel: 'admin'))->assertForbidden();
});
