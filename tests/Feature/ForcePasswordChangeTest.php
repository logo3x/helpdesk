<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('usuario_final', 'web');
    Role::findOrCreate('agente_soporte', 'web');
});

it('redirige a first-change cuando password_must_change es true', function () {
    $user = User::factory()->create([
        'password_must_change' => true,
        'password' => Hash::make('12345678'),
    ]);
    $user->syncRoles(['usuario_final']);

    $this->actingAs($user)
        ->get('/portal/chatbot')
        ->assertRedirect(route('password.first-change'));
});

it('deja pasar cuando password_must_change es false', function () {
    $user = User::factory()->create([
        'password_must_change' => false,
    ]);
    $user->syncRoles(['usuario_final']);

    // El middleware NO debería redirigir. Puede que aún se redirija por
    // otras razones (ASL) — pero NO al flujo de password.
    $response = $this->actingAs($user)->get('/portal/chatbot');
    $target = $response->headers->get('Location') ?? '';
    expect($target)->not->toContain('password/first-change');
});

it('permite acceder a la propia ruta de cambio sin loop', function () {
    $user = User::factory()->create(['password_must_change' => true]);
    $user->syncRoles(['usuario_final']);

    $this->actingAs($user)
        ->get(route('password.first-change'))
        ->assertOk();
});

it('cambia password y limpia el flag', function () {
    $user = User::factory()->create([
        'password' => Hash::make('12345678'),
        'password_must_change' => true,
    ]);
    $user->syncRoles(['usuario_final']);

    $this->actingAs($user)
        ->post(route('password.first-change.update'), [
            'current_password' => '12345678',
            'password' => 'NuevaSegura123',
            'password_confirmation' => 'NuevaSegura123',
        ])
        ->assertRedirect('/portal/chatbot');

    $user->refresh();
    expect($user->password_must_change)->toBeFalse();
    expect(Hash::check('NuevaSegura123', $user->password))->toBeTrue();
});

it('rechaza si repite la misma password temporal', function () {
    $user = User::factory()->create([
        'password' => Hash::make('12345678'),
        'password_must_change' => true,
    ]);
    $user->syncRoles(['usuario_final']);

    $this->actingAs($user)
        ->post(route('password.first-change.update'), [
            'current_password' => '12345678',
            'password' => '12345678',
            'password_confirmation' => '12345678',
        ])
        ->assertSessionHasErrors('password');

    expect($user->fresh()->password_must_change)->toBeTrue();
});

it('rechaza si la current_password no es correcta', function () {
    $user = User::factory()->create([
        'password' => Hash::make('12345678'),
        'password_must_change' => true,
    ]);
    $user->syncRoles(['usuario_final']);

    $this->actingAs($user)
        ->post(route('password.first-change.update'), [
            'current_password' => 'otra-cosa',
            'password' => 'NuevaSegura123',
            'password_confirmation' => 'NuevaSegura123',
        ])
        ->assertSessionHasErrors('current_password');
});

it('rechaza acceso al controller si el usuario NO tiene el flag', function () {
    $user = User::factory()->create(['password_must_change' => false]);
    $user->syncRoles(['usuario_final']);

    $this->actingAs($user)
        ->get(route('password.first-change'))
        ->assertRedirect('/');

    $this->actingAs($user)
        ->post(route('password.first-change.update'), [
            'current_password' => 'x',
            'password' => 'NuevaSegura123',
            'password_confirmation' => 'NuevaSegura123',
        ])
        ->assertForbidden();
});

it('redirige a soporte tras cambiar si el rol es agente', function () {
    $user = User::factory()->create([
        'password' => Hash::make('99999999'),
        'password_must_change' => true,
    ]);
    $user->syncRoles(['agente_soporte']);

    $this->actingAs($user)
        ->post(route('password.first-change.update'), [
            'current_password' => '99999999',
            'password' => 'NuevaSegura123',
            'password_confirmation' => 'NuevaSegura123',
        ])
        ->assertRedirect('/soporte');
});
