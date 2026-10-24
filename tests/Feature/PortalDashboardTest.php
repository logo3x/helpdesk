<?php

use App\Livewire\Portal\Dashboard;
use App\Models\User;
use Livewire\Livewire;

it('el dashboard renderiza con el asistente IA como primer bloque', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Dashboard::class)
        ->assertSee('asistente del Helpdesk Confipetrol');
});

it('el dashboard incluye el input del asistente con name="q"', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Dashboard::class)
        ->assertSee('name="q"', escape: false);
});

it('usa wire:navigate.hover para precarga en los accesos rapidos', function () {
    $contents = file_get_contents(resource_path('views/livewire/portal/dashboard.blade.php'));

    expect($contents)->toContain('wire:navigate.hover');
    expect(substr_count($contents, 'wire:navigate.hover'))->toBeGreaterThanOrEqual(10);
});
