<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users are redirected from dashboard to their panel', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // Sin rol específico → portal del usuario final es el fallback.
    $response = $this->get(route('dashboard'));
    $response->assertRedirect('/portal/tickets');
});
