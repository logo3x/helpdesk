<?php

use App\Enums\MaintenanceFrequency;
use App\Enums\MaintenanceStatus;
use App\Jobs\NotifyDueMaintenancesJob;
use App\Models\Asset;
use App\Models\ScheduledMaintenance;
use App\Models\User;

/**
 * Verifica el job de alerta de vencimiento (30 días default):
 *   - Notifica pendientes con scheduled_at dentro del umbral.
 *   - No re-notifica los que ya tienen notified_due_at seteado.
 *   - Ignora cumplidos y no cumplidos.
 *   - Marca notified_due_at tras notificar.
 */
function makeMaintenance(array $overrides): ScheduledMaintenance
{
    $asset = Asset::factory()->create(['type' => 'laptop']);
    $agent = User::factory()->create();

    return ScheduledMaintenance::create(array_merge([
        'asset_id' => $asset->id,
        'agent_id' => $agent->id,
        'created_by_id' => $agent->id,
        'status' => MaintenanceStatus::Pendiente->value,
        'frequency' => MaintenanceFrequency::Cuatrimestral->value,
    ], $overrides));
}

it('notifica pendientes dentro de la ventana de alerta', function () {
    config()->set('maintenances.alert_days_before', 30);

    $m = makeMaintenance(['scheduled_at' => now()->addDays(20)]);

    (new NotifyDueMaintenancesJob)->handle();

    $m->refresh();
    expect($m->notified_due_at)->not->toBeNull();
});

it('no notifica los que están fuera de la ventana', function () {
    config()->set('maintenances.alert_days_before', 30);

    $m = makeMaintenance(['scheduled_at' => now()->addDays(60)]);

    (new NotifyDueMaintenancesJob)->handle();

    $m->refresh();
    expect($m->notified_due_at)->toBeNull();
});

it('no re-notifica si ya tiene notified_due_at', function () {
    config()->set('maintenances.alert_days_before', 30);

    $prevNotified = now()->subDays(5);
    $m = makeMaintenance([
        'scheduled_at' => now()->addDays(10),
        'notified_due_at' => $prevNotified,
    ]);

    (new NotifyDueMaintenancesJob)->handle();

    $m->refresh();
    expect($m->notified_due_at->toDateTimeString())->toBe($prevNotified->toDateTimeString());
});

it('ignora mantenimientos ya cumplidos o no cumplidos', function () {
    config()->set('maintenances.alert_days_before', 30);

    $cumplido = makeMaintenance(['scheduled_at' => now()->addDays(10)]);
    $cumplido->update(['status' => MaintenanceStatus::Cumplido->value, 'progress_percent' => 100]);
    $cumplido->update(['notified_due_at' => null]); // reset por si el observer o algo tocó

    $noCumplido = makeMaintenance(['scheduled_at' => now()->addDays(10)]);
    $noCumplido->update(['status' => MaintenanceStatus::NoCumplido->value, 'not_completed_reason' => 'x']);
    $noCumplido->update(['notified_due_at' => null]);

    (new NotifyDueMaintenancesJob)->handle();

    expect($cumplido->fresh()->notified_due_at)->toBeNull()
        ->and($noCumplido->fresh()->notified_due_at)->toBeNull();
});
