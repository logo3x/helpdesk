<?php

use App\Enums\MaintenanceFrequency;
use App\Enums\MaintenanceStatus;
use App\Models\Asset;
use App\Models\ScheduledMaintenance;
use App\Models\User;

/**
 * Verifica el flujo de creación individual del módulo Mantenimientos.
 *
 * (La creación masiva desde el form tiene tanto Livewire state como
 * dispatch de eventos que complican simular en Feature test. Se
 * cubre por smoke test manual + al menos verificamos que crear N
 * registros con misma fecha/agente funciona a nivel modelo.)
 */
it('crea un mantenimiento con los campos requeridos', function () {
    $asset = Asset::factory()->create(['type' => 'laptop']);
    $agent = User::factory()->create();

    $m = ScheduledMaintenance::create([
        'asset_id' => $asset->id,
        'agent_id' => $agent->id,
        'created_by_id' => $agent->id,
        'scheduled_at' => now()->addDays(30),
        'status' => MaintenanceStatus::Pendiente,
        'frequency' => MaintenanceFrequency::Cuatrimestral,
    ]);

    expect($m->id)->toBeGreaterThan(0)
        ->and($m->status)->toBe(MaintenanceStatus::Pendiente)
        ->and($m->frequency)->toBe(MaintenanceFrequency::Cuatrimestral)
        ->and($m->frequencyDays())->toBe(120);
});

it('crea N mantenimientos con misma fecha, agente y frecuencia (bulk simulado)', function () {
    $agent = User::factory()->create();
    $assets = Asset::factory()->count(3)->create(['type' => 'desktop']);
    $scheduledAt = now()->addDays(14);

    foreach ($assets as $asset) {
        ScheduledMaintenance::create([
            'asset_id' => $asset->id,
            'agent_id' => $agent->id,
            'created_by_id' => $agent->id,
            'scheduled_at' => $scheduledAt,
            'status' => MaintenanceStatus::Pendiente,
            'frequency' => MaintenanceFrequency::Anual,
        ]);
    }

    $count = ScheduledMaintenance::where('agent_id', $agent->id)
        ->whereDate('scheduled_at', $scheduledAt->toDateString())
        ->count();

    expect($count)->toBe(3);
});

it('frecuencia anual convierte a 365 días', function () {
    $m = ScheduledMaintenance::create([
        'asset_id' => Asset::factory()->create(['type' => 'server'])->id,
        'agent_id' => User::factory()->create()->id,
        'created_by_id' => User::factory()->create()->id,
        'scheduled_at' => now()->addDays(30),
        'status' => MaintenanceStatus::Pendiente,
        'frequency' => MaintenanceFrequency::Anual,
    ]);

    expect($m->frequencyDays())->toBe(365);
});
