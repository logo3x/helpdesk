<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Tenant y dominio autorizado — imprescindibles para pasar los guards.
    config([
        'services.azure.tenant_id' => 'test-tenant-id',
        'services.azure.allowed_domains' => 'confipetrol.com',
    ]);

    foreach (['usuario_final', 'agente_soporte', 'super_admin'] as $r) {
        Role::findOrCreate($r, 'web');
    }
});

/**
 * Fabrica un SocialiteUser con oid, email, name y un `user` claim
 * bag (donde vive `employeeId` u otras extensiones custom del tenant).
 *
 * @param  array<string, mixed>  $claims
 */
function fakeAzureUser(string $oid, string $email, string $name, array $claims = []): SocialiteUser
{
    $u = new SocialiteUser;
    $u->id = $oid;
    $u->email = $email;
    $u->name = $name;
    $u->user = $claims;

    return $u;
}

function stubSocialite(SocialiteUser $azureUser): void
{
    $driver = Mockery::mock();
    $driver->shouldReceive('stateless')->andReturnSelf();
    $driver->shouldReceive('user')->andReturn($azureUser);

    Socialite::shouldReceive('driver')
        ->with('microsoft')
        ->andReturn($driver);
}

it('reconoce por azure_id aunque el email haya cambiado', function () {
    $user = User::factory()->create([
        'azure_id' => 'oid-stable-1',
        'email' => 'viejo@confipetrol.com',
    ]);

    stubSocialite(fakeAzureUser('oid-stable-1', 'nuevo@confipetrol.com', 'Nombre X'));

    $this->get('/auth/azure/callback')->assertRedirect();

    $user->refresh();
    expect($user->email)->toBe('nuevo@confipetrol.com'); // se sincroniza al email real de Azure
    expect($user->azure_id)->toBe('oid-stable-1');
});

it('activa el stub Azure pending cuando entra por primera vez', function () {
    $stub = User::factory()->create([
        'azure_id' => null,
        'email' => 'luis.oviedo@confipetrol.com',
        'identification' => '1121898647',
        'is_azure_pending' => true,
        'azure_first_login_at' => null,
    ]);
    $stub->syncRoles(['agente_soporte']);

    stubSocialite(fakeAzureUser('new-oid-42', 'luis.oviedo@confipetrol.com', 'Luis Oviedo'));

    $this->get('/auth/azure/callback')->assertRedirect();

    $stub->refresh();
    expect($stub->azure_id)->toBe('new-oid-42');
    expect($stub->is_azure_pending)->toBeFalse();
    expect($stub->azure_first_login_at)->not->toBeNull();
    // Rol debe permanecer intocado (fix del Sprint 1).
    expect($stub->hasRole('agente_soporte'))->toBeTrue();
});

it('matchea por cedula cuando el email precargado no coincide', function () {
    // RRHH precargó al usuario con un email placeholder; Azure devuelve
    // el email corporativo real y `employeeId` con la cédula.
    $stub = User::factory()->create([
        'azure_id' => null,
        'email' => 'placeholder@confipetrol.com',
        'identification' => '1121898647',
        'is_azure_pending' => true,
    ]);

    stubSocialite(fakeAzureUser(
        'oid-cedula-match',
        'luis.oviedo@confipetrol.com',
        'Luis Oviedo',
        ['employeeId' => '1121898647'],
    ));

    $this->get('/auth/azure/callback')->assertRedirect();

    $stub->refresh();
    expect($stub->azure_id)->toBe('oid-cedula-match');
    // Email se corrige al verdadero de Azure.
    expect($stub->email)->toBe('luis.oviedo@confipetrol.com');
    expect($stub->is_azure_pending)->toBeFalse();
});

it('no matchea por cedula si el usuario ya esta activo (evita hijack)', function () {
    // Alguien ya activo con esa cédula — NO debe pisarse aunque Azure
    // traiga esa misma cédula bajo otro oid/email.
    $active = User::factory()->create([
        'azure_id' => 'oid-original',
        'email' => 'original@confipetrol.com',
        'identification' => '1121898647',
        'is_azure_pending' => false,
    ]);

    stubSocialite(fakeAzureUser(
        'oid-different',
        'atacante@confipetrol.com',
        'Otro',
        ['employeeId' => '1121898647'],
    ));

    $this->get('/auth/azure/callback')->assertRedirect();

    // El usuario original queda intacto.
    $active->refresh();
    expect($active->email)->toBe('original@confipetrol.com');
    expect($active->azure_id)->toBe('oid-original');

    // Y se crea uno nuevo para el que vino por Azure.
    $newUser = User::where('azure_id', 'oid-different')->first();
    expect($newUser)->not->toBeNull();
    expect($newUser->email)->toBe('atacante@confipetrol.com');
});

it('crea usuario nuevo cuando nada matchea y guarda cedula si viene', function () {
    stubSocialite(fakeAzureUser(
        'oid-nuevo',
        'nuevo.empleado@confipetrol.com',
        'Nuevo Empleado',
        ['employeeId' => '9876543210'],
    ));

    $this->get('/auth/azure/callback')->assertRedirect();

    $u = User::where('email', 'nuevo.empleado@confipetrol.com')->first();
    expect($u)->not->toBeNull();
    expect($u->identification)->toBe('9876543210');
    expect($u->is_azure_pending)->toBeFalse();
    expect($u->azure_first_login_at)->not->toBeNull();
});

it('rechaza email fuera del dominio autorizado', function () {
    stubSocialite(fakeAzureUser('oid-x', 'hacker@gmail.com', 'Hacker'));

    $this->get('/auth/azure/callback')->assertForbidden();
});
