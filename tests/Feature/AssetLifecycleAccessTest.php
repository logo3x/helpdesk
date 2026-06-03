<?php

use App\Models\Asset;

it('expone la ruta de hoja de vida en el panel admin', function () {
    $asset = Asset::create([
        'asset_tag' => 'LIFE-001',
        'status' => 'active',
    ]);

    $url = route('filament.admin.resources.assets.lifecycle', ['record' => $asset]);

    expect($url)->toContain("/admin/assets/{$asset->id}/lifecycle");
});

it('expone la ruta de hoja de vida en el panel soporte', function () {
    $asset = Asset::create([
        'asset_tag' => 'LIFE-002',
        'status' => 'active',
    ]);

    $url = route('filament.soporte.resources.assets.lifecycle', ['record' => $asset]);

    expect($url)->toContain("/soporte/assets/{$asset->id}/lifecycle");
});
