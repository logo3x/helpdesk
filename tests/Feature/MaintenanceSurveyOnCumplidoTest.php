<?php

use App\Enums\MaintenanceStatus;
use App\Jobs\SendMaintenanceSurveyJob;
use App\Models\Asset;
use App\Models\MaintenanceSurvey;
use App\Models\ScheduledMaintenance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('dispara SendMaintenanceSurveyJob al cerrar un mantenimiento en Cumplido', function () {
    Queue::fake();

    $custodian = User::factory()->create();
    $asset = Asset::factory()->create(['user_id' => $custodian->id]);
    $mtto = ScheduledMaintenance::factory()->create([
        'asset_id' => $asset->id,
        'status' => MaintenanceStatus::Pendiente,
    ]);

    $mtto->update(['status' => MaintenanceStatus::Cumplido]);

    Queue::assertPushed(SendMaintenanceSurveyJob::class, function (SendMaintenanceSurveyJob $job) use ($asset) {
        return $job->asset->is($asset);
    });
});

it('NO dispara encuesta al cerrar en No Cumplido', function () {
    Queue::fake();

    $custodian = User::factory()->create();
    $asset = Asset::factory()->create(['user_id' => $custodian->id]);
    $mtto = ScheduledMaintenance::factory()->create([
        'asset_id' => $asset->id,
        'status' => MaintenanceStatus::Pendiente,
    ]);

    $mtto->update([
        'status' => MaintenanceStatus::NoCumplido,
        'not_completed_reason' => 'Repuesto pendiente',
    ]);

    Queue::assertNotPushed(SendMaintenanceSurveyJob::class);
});

it('NO dispara encuesta si el activo no tiene custodio', function () {
    Queue::fake();

    $asset = Asset::factory()->create(['user_id' => null]);
    $mtto = ScheduledMaintenance::factory()->create([
        'asset_id' => $asset->id,
        'status' => MaintenanceStatus::Pendiente,
    ]);

    $mtto->update(['status' => MaintenanceStatus::Cumplido]);

    Queue::assertNotPushed(SendMaintenanceSurveyJob::class);
});

it('el job real crea el MaintenanceSurvey y evita duplicados', function () {
    $custodian = User::factory()->create();
    $asset = Asset::factory()->create(['user_id' => $custodian->id]);

    // Primer dispatch — crea el survey.
    SendMaintenanceSurveyJob::dispatchSync($asset);

    expect(MaintenanceSurvey::query()
        ->where('asset_id', $asset->id)
        ->where('user_id', $custodian->id)
        ->count())->toBe(1);

    // Segundo dispatch mientras el survey sigue pendiente — no duplica.
    SendMaintenanceSurveyJob::dispatchSync($asset);

    expect(MaintenanceSurvey::query()
        ->where('asset_id', $asset->id)
        ->where('user_id', $custodian->id)
        ->count())->toBe(1);
});
