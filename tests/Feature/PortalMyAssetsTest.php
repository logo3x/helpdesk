<?php

use App\Livewire\Portal\MyAssets;
use App\Models\Asset;
use App\Models\AssetHandover;
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

it('confirma un handover pendiente y setea received_confirmed_at', function () {
    $user = User::factory()->create();
    $asset = Asset::create(['asset_tag' => 'X-1', 'status' => 'active', 'user_id' => $user->id]);
    $handover = AssetHandover::create([
        'acta_number' => 1000, 'asset_id' => $asset->id,
        'received_by_user_id' => $user->id,
        'delivered_at' => now()->subDay(),
        'condition_at_delivery' => 'bueno',
        'template_version' => 'IT-ADM1-F-5_v3',
    ]);

    Livewire::actingAs($user)
        ->test(MyAssets::class)
        ->call('confirmHandover', $handover->id);

    expect($handover->fresh()->received_confirmed_at)->not->toBeNull();
});

it('rechaza confirmar handovers de otro usuario', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $asset = Asset::create(['asset_tag' => 'X-2', 'status' => 'active', 'user_id' => $bob->id]);
    $handover = AssetHandover::create([
        'acta_number' => 1001, 'asset_id' => $asset->id,
        'received_by_user_id' => $bob->id,
        'delivered_at' => now()->subDay(),
        'condition_at_delivery' => 'bueno',
        'template_version' => 'IT-ADM1-F-5_v3',
    ]);

    Livewire::actingAs($alice)
        ->test(MyAssets::class)
        ->call('confirmHandover', $handover->id);

    expect($handover->fresh()->received_confirmed_at)->toBeNull();
});

it('no resetea received_confirmed_at si ya estaba confirmado', function () {
    $user = User::factory()->create();
    $asset = Asset::create(['asset_tag' => 'X-3', 'status' => 'active', 'user_id' => $user->id]);
    $alreadyConfirmedAt = now()->subDays(2);
    $handover = AssetHandover::create([
        'acta_number' => 1002, 'asset_id' => $asset->id,
        'received_by_user_id' => $user->id,
        'delivered_at' => now()->subDays(5),
        'received_confirmed_at' => $alreadyConfirmedAt,
        'condition_at_delivery' => 'bueno',
        'template_version' => 'IT-ADM1-F-5_v3',
    ]);

    Livewire::actingAs($user)
        ->test(MyAssets::class)
        ->call('confirmHandover', $handover->id);

    expect($handover->fresh()->received_confirmed_at->timestamp)->toBe($alreadyConfirmedAt->timestamp);
});
