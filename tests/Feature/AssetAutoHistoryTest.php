<?php

use App\Models\Asset;
use App\Models\Department;
use App\Models\User;

it('crea AssetHistory cuando cambia un campo tracked', function () {
    $asset = Asset::create([
        'asset_tag' => 'ABC-001',
        'status' => 'active',
        'field' => 'PORE',
    ]);

    expect($asset->histories()->count())->toBe(0);

    $asset->update(['field' => 'CARUPANA']);

    $history = $asset->histories()->latest('id')->first();

    expect($history)->not->toBeNull()
        ->and($history->action)->toBe('updated')
        ->and($history->field)->toBe('field')
        ->and($history->old_value)->toBe('PORE')
        ->and($history->new_value)->toBe('CARUPANA');
});

it('crea una entrada por cada campo tracked modificado en el mismo save', function () {
    $asset = Asset::create([
        'asset_tag' => 'ABC-002',
        'status' => 'active',
        'field' => 'PORE',
        'location_zone' => 'ZONA 1',
    ]);

    $asset->update([
        'field' => 'CARUPANA',
        'location_zone' => 'ZONA 4',
        'management_area' => 'HSEQ',
    ]);

    $fields = $asset->histories()->where('action', 'updated')->pluck('field')->all();

    expect($fields)->toContain('field')
        ->and($fields)->toContain('location_zone')
        ->and($fields)->toContain('management_area');
});

it('ignora cambios en campos volátiles del scan', function () {
    $asset = Asset::create([
        'asset_tag' => 'ABC-003',
        'status' => 'active',
    ]);

    $asset->update([
        'ip_address' => '10.0.0.5',
        'mac_address' => 'AA:BB:CC:DD:EE:FF',
        'last_scan_at' => now(),
    ]);

    expect($asset->histories()->count())->toBe(0);
});

it('respeta skipAutoHistory para acciones que crean su propia entrada', function () {
    $asset = Asset::create([
        'asset_tag' => 'ABC-004',
        'status' => 'active',
        'field' => 'PORE',
    ]);

    $asset->skipAutoHistory = true;
    $asset->update(['field' => 'CARUPANA']);

    expect($asset->histories()->count())->toBe(0);
});

it('registra cambios sin user autenticado dejando user_id null', function () {
    $asset = Asset::create([
        'asset_tag' => 'ABC-005',
        'status' => 'active',
    ]);

    $asset->update(['notes' => 'observación nueva']);

    expect($asset->histories()->first()->user_id)->toBeNull();
});

it('atribuye el cambio al usuario autenticado', function () {
    $dept = Department::create(['name' => 'TI', 'slug' => 'ti']);
    $user = User::factory()->create(['department_id' => $dept->id]);
    $this->actingAs($user);

    $asset = Asset::create([
        'asset_tag' => 'ABC-006',
        'status' => 'active',
    ]);

    $asset->update(['status' => 'maintenance']);

    expect($asset->histories()->latest('id')->first()->user_id)->toBe($user->id);
});
