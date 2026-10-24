<?php

use App\Enums\MaintenanceStatus;
use App\Filament\Soporte\Pages\MaintenancesReport;
use App\Models\Asset;
use App\Models\ScheduledMaintenance;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'supervisor_soporte', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'agente_soporte', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
    $this->actingAs($this->admin);
});

it('filtra el informe por gerencia (management_area)', function () {
    $assetHSEQ = Asset::factory()->create(['management_area' => 'HSEQ']);
    $assetOps = Asset::factory()->create(['management_area' => 'Operaciones']);

    ScheduledMaintenance::factory()->create([
        'asset_id' => $assetHSEQ->id,
        'status' => MaintenanceStatus::Cumplido,
        'scheduled_at' => now()->subDays(10),
    ]);
    ScheduledMaintenance::factory()->create([
        'asset_id' => $assetOps->id,
        'status' => MaintenanceStatus::Cumplido,
        'scheduled_at' => now()->subDays(10),
    ]);

    $page = new MaintenancesReport;
    $page->managementArea = 'HSEQ';

    $data = $page->getViewData();

    expect($data['kpi']['total'])->toBe(1);
    expect($data['activeFilters'])->toHaveKey('Gerencia');
    expect($data['activeFilters']['Gerencia'])->toBe('HSEQ');
});

it('filtra por agente', function () {
    $agentA = User::factory()->create(['name' => 'Agente A']);
    $agentB = User::factory()->create(['name' => 'Agente B']);
    $agentA->assignRole('agente_soporte');
    $agentB->assignRole('agente_soporte');

    $asset = Asset::factory()->create();

    ScheduledMaintenance::factory()->create([
        'asset_id' => $asset->id,
        'agent_id' => $agentA->id,
        'scheduled_at' => now()->subDays(5),
    ]);
    ScheduledMaintenance::factory()->create([
        'asset_id' => $asset->id,
        'agent_id' => $agentB->id,
        'scheduled_at' => now()->subDays(5),
    ]);

    $page = new MaintenancesReport;
    $page->agentId = $agentA->id;

    expect($page->getViewData()['kpi']['total'])->toBe(1);
});

it('calcula distribución por gerencia con porcentaje de cumplimiento', function () {
    $asset = Asset::factory()->create(['management_area' => 'HSEQ']);

    ScheduledMaintenance::factory()->create([
        'asset_id' => $asset->id,
        'status' => MaintenanceStatus::Cumplido,
        'scheduled_at' => now()->subDays(10),
    ]);
    ScheduledMaintenance::factory()->create([
        'asset_id' => $asset->id,
        'status' => MaintenanceStatus::NoCumplido,
        'scheduled_at' => now()->subDays(10),
    ]);

    $page = new MaintenancesReport;
    $data = $page->getViewData();

    expect($data['byManagementArea'])->toHaveCount(1);
    expect($data['byManagementArea'][0]['label'])->toBe('HSEQ');
    expect($data['byManagementArea'][0]['total'])->toBe(2);
    expect($data['byManagementArea'][0]['cumplidos'])->toBe(1);
    expect($data['byManagementArea'][0]['compliance_pct'])->toBe(50);
});

it('filterOptions incluye catálogos no vacíos de los activos existentes', function () {
    Asset::factory()->create(['management_area' => 'HSEQ', 'field' => 'PORE', 'location_zone' => 'ZONA 1']);
    Asset::factory()->create(['management_area' => 'Operaciones', 'field' => 'CARUPANA', 'location_zone' => 'ZONA 2']);

    $page = new MaintenancesReport;
    $data = $page->getViewData();

    expect(array_keys($data['filterOptions']['managementAreas']))
        ->toContain('HSEQ')
        ->toContain('Operaciones');
    expect(array_keys($data['filterOptions']['zones']))
        ->toContain('ZONA 1')
        ->toContain('ZONA 2');
});
