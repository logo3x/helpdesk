<?php

use App\Models\User;
use App\Services\BackupManager;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Link de descarga de respaldos: firma temporal + sesión de admin, y el
 * nombre se valida contra los archivos reales del disco.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('backups');
    Storage::disk('backups')->put('helpdesk-completo/completo-2026-11-01-02-30-00.zip', 'contenido-zip');
    Storage::fake('local');
    Storage::disk('local')->put('secreto.zip', 'no-debe-salir');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('descarga el respaldo con link firmado y sesión de admin', function () {
    $url = app(BackupManager::class)->downloadUrl('helpdesk-completo/completo-2026-11-01-02-30-00.zip');

    $response = $this->actingAs($this->admin)->get($url);

    $response->assertOk()
        ->assertDownload('completo-2026-11-01-02-30-00.zip');
    expect($response->streamedContent())->toBe('contenido-zip');
});

it('también permite la descarga a super_admin', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');

    $this->actingAs($superAdmin)
        ->get(app(BackupManager::class)->downloadUrl('helpdesk-completo/completo-2026-11-01-02-30-00.zip'))
        ->assertOk();
});

it('da 403 a un usuario que no es admin aunque el link esté firmado', function (string $role) {
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(app(BackupManager::class)->downloadUrl('helpdesk-completo/completo-2026-11-01-02-30-00.zip'))
        ->assertForbidden();
})->with(['agente_soporte', 'supervisor_soporte', 'usuario_final']);

it('exige iniciar sesión aunque el link esté firmado', function () {
    $this->get(app(BackupManager::class)->downloadUrl('helpdesk-completo/completo-2026-11-01-02-30-00.zip'))
        ->assertRedirect();
});

it('da 403 con firma inválida, sin firma o vencida', function () {
    $url = app(BackupManager::class)->downloadUrl('helpdesk-completo/completo-2026-11-01-02-30-00.zip', now()->addHour());

    $this->actingAs($this->admin)->get($url.'x')->assertForbidden();
    $this->actingAs($this->admin)->get('/respaldos/descargar/helpdesk-completo/completo-2026-11-01-02-30-00.zip')->assertForbidden();

    $this->travel(2)->hours();
    $this->actingAs($this->admin)->get($url)->assertForbidden();
});

it('da 404 si el nombre trae ".." o no existe en el disco de respaldos', function (string $folder, string $file) {
    $url = URL::temporarySignedRoute('backups.download', now()->addHour(), ['folder' => $folder, 'file' => $file]);

    $this->actingAs($this->admin)->get($url)->assertNotFound();
})->with([
    'carpeta ..' => ['..', 'secreto.zip'],
    'archivo con ..' => ['helpdesk-completo', '..'],
    'archivo con barra invertida' => ['helpdesk-completo', '..\\..\\secreto.zip'],
    'no es zip' => ['helpdesk-completo', '.env'],
    'archivo inexistente' => ['helpdesk-completo', 'completo-1999-01-01-00-00-00.zip'],
    'carpeta inexistente' => ['otra', 'completo-2026-11-01-02-30-00.zip'],
]);

it('registra la descarga en la bitácora', function () {
    $this->actingAs($this->admin)
        ->get(app(BackupManager::class)->downloadUrl('helpdesk-completo/completo-2026-11-01-02-30-00.zip'))
        ->assertOk();

    $this->assertDatabaseHas('activity_log', [
        'log_name' => BackupManager::LOG_NAME,
        'event' => 'downloaded',
        'causer_id' => $this->admin->id,
    ]);
});
