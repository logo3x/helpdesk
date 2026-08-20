<?php

use App\Enums\MaintenanceFrequency;
use App\Enums\MaintenanceStatus;
use App\Models\Asset;
use App\Models\AssetHistory;
use App\Models\ScheduledMaintenance;
use App\Models\User;

/**
 * Verifica el ciclo de vida controlado por ScheduledMaintenanceObserver:
 *  1. Al cerrar un ciclo (cumplido/no_cumplido) escribe en AssetHistory.
 *  2. Al cumplir, actualiza last_maintenance_at + maintenance_interval_days
 *     del Asset.
 *  3. Genera siempre la siguiente ocurrencia con scheduled_at =
 *     fecha_original + días_de_la_frecuencia.
 *  4. No genera doble ocurrencia si se re-guarda el mismo registro.
 */
function createMaintenanceForTest(array $overrides = []): ScheduledMaintenance
{
    $asset = Asset::factory()->create(['type' => 'laptop']);
    $agent = User::factory()->create();

    return ScheduledMaintenance::create(array_merge([
        'asset_id' => $asset->id,
        'agent_id' => $agent->id,
        'created_by_id' => $agent->id,
        'scheduled_at' => now()->addDays(30),
        'status' => MaintenanceStatus::Pendiente->value,
        'frequency' => MaintenanceFrequency::Cuatrimestral->value,
    ], $overrides));
}

it('al cumplir registra la entrada en historial del activo', function () {
    $m = createMaintenanceForTest(['scheduled_at' => now()->subDays(1)]);

    $m->update([
        'status' => MaintenanceStatus::Cumplido->value,
        'progress_percent' => 100,
        'observations' => 'Limpieza + pasta térmica',
    ]);

    $history = AssetHistory::where('asset_id', $m->asset_id)
        ->where('action', 'maintenance')
        ->latest('id')
        ->first();

    expect($history)->not->toBeNull()
        ->and($history->notes)->toContain('Cumplido')
        ->and($history->notes)->toContain('100%')
        ->and($history->notes)->toContain('Limpieza + pasta térmica');
});

it('al cumplir actualiza last_maintenance_at del activo', function () {
    $m = createMaintenanceForTest();

    $m->update([
        'status' => MaintenanceStatus::Cumplido->value,
        'progress_percent' => 100,
    ]);

    $asset = $m->asset->fresh();
    expect($asset->last_maintenance_at)->not->toBeNull()
        ->and($asset->maintenance_interval_days)->toBe(120);
});

it('al cumplir genera la siguiente ocurrencia sumando la frecuencia a la fecha original', function () {
    $scheduled = now()->addDays(30)->startOfDay();
    $m = createMaintenanceForTest([
        'scheduled_at' => $scheduled,
        'frequency' => MaintenanceFrequency::Cuatrimestral->value,
    ]);

    $m->update(['status' => MaintenanceStatus::Cumplido->value, 'progress_percent' => 100]);

    $next = ScheduledMaintenance::where('parent_id', $m->id)->first();
    expect($next)->not->toBeNull()
        ->and($next->scheduled_at->toDateString())->toBe($scheduled->copy()->addDays(120)->toDateString())
        ->and($next->status)->toBe(MaintenanceStatus::Pendiente)
        ->and($next->agent_id)->toBe($m->agent_id);
});

it('al marcar no_cumplido igual genera siguiente ocurrencia (mantiene ciclo)', function () {
    $scheduled = now()->subDays(5);
    $m = createMaintenanceForTest([
        'scheduled_at' => $scheduled,
        'frequency' => MaintenanceFrequency::Anual->value,
    ]);

    $m->update([
        'status' => MaintenanceStatus::NoCumplido->value,
        'not_completed_reason' => 'Usuario en vacaciones',
    ]);

    $next = ScheduledMaintenance::where('parent_id', $m->id)->first();
    expect($next)->not->toBeNull()
        ->and($next->scheduled_at->toDateString())->toBe($scheduled->copy()->addDays(365)->toDateString());
});

it('no genera doble ocurrencia si el mtto se re-guarda', function () {
    $m = createMaintenanceForTest();
    $m->update(['status' => MaintenanceStatus::Cumplido->value, 'progress_percent' => 100]);

    // Guardo de nuevo (por ej. edición posterior de observations)
    $m->update(['observations' => 'Edit posterior']);

    $count = ScheduledMaintenance::where('parent_id', $m->id)->count();
    expect($count)->toBe(1);
});

it('isOverdue() detecta vencidos pendientes', function () {
    $future = createMaintenanceForTest(['scheduled_at' => now()->addDays(10)]);
    $past = createMaintenanceForTest(['scheduled_at' => now()->subDays(3)]);
    $completed = createMaintenanceForTest(['scheduled_at' => now()->subDays(3)]);
    $completed->update(['status' => MaintenanceStatus::Cumplido->value, 'progress_percent' => 100]);

    expect($future->isOverdue())->toBeFalse()
        ->and($past->fresh()->isOverdue())->toBeTrue()
        ->and($completed->fresh()->isOverdue())->toBeFalse();
});
