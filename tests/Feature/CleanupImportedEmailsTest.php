<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('dry-run no modifica usuarios pero los reporta', function () {
    User::factory()->create([
        'email' => 'test@imported.local',
        'identification' => '1111',
        'is_azure_pending' => false,
    ]);

    $this->artisan('users:cleanup-imported-emails', ['--dry-run' => true])
        ->assertSuccessful();

    $u = User::where('identification', '1111')->first();
    expect($u->email)->toBe('test@imported.local');
    expect($u->is_azure_pending)->toBeFalse();
});

it('json emite un reporte estructurado', function () {
    User::factory()->create([
        'email' => 'con-cedula@imported.local',
        'identification' => '2222',
    ]);
    User::factory()->create([
        'email' => 'sin-cedula@imported.local',
        'identification' => null,
    ]);

    // Capturamos el output ejecutando el comando y leyendo Artisan::output().
    Artisan::call('users:cleanup-imported-emails', ['--json' => true]);
    $output = Artisan::output();

    expect($output)->toContain('"ready"');
    expect($output)->toContain('"missing_identification"');
    expect($output)->toContain('2222');
    expect($output)->toContain('sin-cedula@imported.local');
});

it('con confirmacion aplica limpieza — email null e is_azure_pending true', function () {
    User::factory()->create([
        'email' => 'existente@imported.local',
        'identification' => '3333',
        'is_azure_pending' => false,
    ]);

    $this->artisan('users:cleanup-imported-emails')
        ->expectsConfirmation('¿Aplicar limpieza a los 1 usuarios con cédula?', 'yes')
        ->assertSuccessful();

    $u = User::where('identification', '3333')->first();
    expect($u->email)->toBeNull();
    expect($u->is_azure_pending)->toBeTrue();
});

it('sin confirmacion no toca nada', function () {
    User::factory()->create([
        'email' => 'x@imported.local',
        'identification' => '4444',
        'is_azure_pending' => false,
    ]);

    $this->artisan('users:cleanup-imported-emails')
        ->expectsConfirmation('¿Aplicar limpieza a los 1 usuarios con cédula?', 'no')
        ->assertSuccessful();

    $u = User::where('identification', '4444')->first();
    expect($u->email)->toBe('x@imported.local');
});

it('usuarios sin cedula NO se tocan aunque tengan email fabricado', function () {
    User::factory()->create([
        'email' => 'huerfano@imported.local',
        'identification' => null,
    ]);

    $this->artisan('users:cleanup-imported-emails', ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Sin cédula');

    $u = User::where('email', 'huerfano@imported.local')->first();
    expect($u->email)->toBe('huerfano@imported.local');
});

it('tambien limpia el dominio sin-email.local', function () {
    User::factory()->create([
        'email' => '5555@sin-email.local',
        'identification' => '5555',
    ]);

    $this->artisan('users:cleanup-imported-emails')
        ->expectsConfirmation('¿Aplicar limpieza a los 1 usuarios con cédula?', 'yes')
        ->assertSuccessful();

    $u = User::where('identification', '5555')->first();
    expect($u->email)->toBeNull();
    expect($u->is_azure_pending)->toBeTrue();
});

it('no cuenta usuarios con email real como candidatos', function () {
    User::factory()->create([
        'email' => 'real@confipetrol.com',
        'identification' => '9999',
    ]);

    $this->artisan('users:cleanup-imported-emails', ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Nada que limpiar');
});
