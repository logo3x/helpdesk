<?php

use App\Models\User;

it('marca asl_accepted_at al hacer POST a la ruta de aceptación', function () {
    $user = User::factory()->create(['asl_accepted_at' => null]);

    $this->actingAs($user)
        ->post(route('asl.accept'))
        ->assertRedirect();

    expect($user->fresh()->asl_accepted_at)->not->toBeNull();
});

it('no sobrescribe la fecha si el usuario ya aceptó previamente', function () {
    $accepted = now()->subWeek();
    $user = User::factory()->create(['asl_accepted_at' => $accepted]);

    $this->actingAs($user)
        ->post(route('asl.accept'))
        ->assertRedirect();

    expect($user->fresh()->asl_accepted_at->timestamp)->toBe($accepted->timestamp);
});

it('la vista de aceptación responde 200 para un usuario pendiente', function () {
    $this->withoutVite();

    $user = User::factory()->create(['asl_accepted_at' => null]);

    $this->actingAs($user)
        ->get(route('asl.show'))
        ->assertOk()
        ->assertSee('Acuerdo de Servicio');
});

it('redirige al home si el usuario ya aceptó y entra a la vista', function () {
    $user = User::factory()->create(['asl_accepted_at' => now()]);

    $this->actingAs($user)
        ->get(route('asl.show'))
        ->assertRedirect();
});

it('rechaza la aceptación de invitados', function () {
    $this->post(route('asl.accept'))
        ->assertRedirect(route('login'));
});

it('middleware redirige al ASL si el usuario no aceptó', function () {
    $this->withoutVite();

    $user = User::factory()->create(['asl_accepted_at' => null]);

    $this->actingAs($user)
        ->get('/portal')
        ->assertRedirect(route('asl.show'));
});

it('middleware deja pasar si el usuario ya aceptó', function () {
    $this->withoutVite();

    $user = User::factory()->create([
        'asl_accepted_at' => now(),
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get('/portal');

    // Cualquier cosa distinta de redirect a /asl/accept está bien:
    // puede ser 200, 403 (sin rol), etc. — el middleware no interceptó.
    expect($response->headers->get('Location'))->not->toBe(route('asl.show'));
});
