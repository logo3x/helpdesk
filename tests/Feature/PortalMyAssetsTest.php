<?php

use App\Livewire\Portal\MyAssets;
use App\Models\Asset;
use App\Models\User;
use Livewire\Livewire;

it('lista solo los activos del usuario autenticado', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Asset::create(['asset_tag' => 'A-1', 'status' => 'active', 'user_id' => $alice->id]);
    Asset::create(['asset_tag' => 'A-2', 'status' => 'active', 'user_id' => $alice->id]);
    Asset::create(['asset_tag' => 'B-1', 'status' => 'active', 'user_id' => $bob->id]);

    Livewire::actingAs($alice)
        ->test(MyAssets::class)
        ->assertSee('A-1')
        ->assertSee('A-2')
        ->assertDontSee('B-1');
});

it('filtra por búsqueda en TAG/hostname/serial/fabricante/modelo', function () {
    $user = User::factory()->create();
    Asset::create(['asset_tag' => 'LAPT-001', 'hostname' => 'pc-juan', 'manufacturer' => 'Dell', 'model' => 'Latitude', 'status' => 'active', 'user_id' => $user->id]);
    Asset::create(['asset_tag' => 'LAPT-002', 'hostname' => 'pc-ana', 'manufacturer' => 'HP', 'model' => 'EliteBook', 'status' => 'active', 'user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(MyAssets::class)
        ->set('search', 'Dell')
        ->assertSee('LAPT-001')
        ->assertDontSee('LAPT-002');
});

it('muestra mensaje vacío cuando el usuario no tiene activos', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(MyAssets::class)
        ->assertSee('Aún no tienes activos asignados');
});
