<?php

use App\Models\Asset;
use App\Models\User;
use App\Services\InventoryService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->service = app(InventoryService::class);
});

it('captures agent_version and scan_status when the v2 agent posts a scan', function () {
    $asset = $this->service->processAgentScan([
        'hostname' => 'PC-T-001',
        'serial_number' => 'SN-T-001',
        'type' => 'laptop',
        'manufacturer' => 'HP',
        'model' => 'Elitebook 840',
        'os_name' => 'Microsoft Windows 11 Pro',
        'os_version' => '10.0.22631',
        'cpu_cores' => 8,
        'ram_mb' => 16384,
        'disk_total_gb' => 512,
        'agent_version' => '2.0.0',
        'scan_status' => 'ok',
    ]);

    expect($asset->hostname)->toBe('PC-T-001');
    expect($asset->agent_version)->toBe('2.0.0');
    expect($asset->last_scan_status)->toBe('ok');
    expect($asset->last_scan_at)->not->toBeNull();
});

it('keeps null agent_version when a legacy v1 agent posts a scan', function () {
    // Un primer scan v1 (sin campos nuevos) deja agent_version=null.
    $asset = $this->service->processAgentScan([
        'hostname' => 'PC-T-002',
        'serial_number' => 'SN-T-002',
        'type' => 'desktop',
    ]);

    expect($asset->agent_version)->toBeNull();
    expect($asset->last_scan_status)->toBeNull();
});

it('preserves a previous agent_version on a partial scan when status indicates failure', function () {
    // Primer scan: agente v2 OK.
    $this->service->processAgentScan([
        'hostname' => 'PC-T-003',
        'agent_version' => '2.0.0',
        'scan_status' => 'ok',
        'ram_mb' => 8192,
    ]);

    // Segundo scan: el mismo agente reporta status=partial pero la
    // versión sigue siendo 2.0.0 — debe quedar reflejado.
    $asset = $this->service->processAgentScan([
        'hostname' => 'PC-T-003',
        'agent_version' => '2.0.0',
        'scan_status' => 'partial',
    ]);

    expect($asset->agent_version)->toBe('2.0.0');
    expect($asset->last_scan_status)->toBe('partial');
    expect($asset->ram_mb)->toBe(8192); // se preserva del primer scan
});

it('exposes stale agents via the scopeStaleAgent query scope', function () {
    Asset::factory()->create([
        'hostname' => 'PC-FRESH',
        'last_scan_at' => now()->subDays(2),
        'status' => 'active',
    ]);

    Asset::factory()->create([
        'hostname' => 'PC-STALE',
        'last_scan_at' => now()->subDays(20),
        'status' => 'active',
    ]);

    Asset::factory()->create([
        'hostname' => 'PC-RETIRED',
        'last_scan_at' => now()->subDays(60),
        'status' => 'retired',
    ]);

    $stale = Asset::query()->staleAgent(daysSilent: 14)->pluck('hostname')->all();

    expect($stale)->toBe(['PC-STALE']);
});

it('rejects an agent_version longer than 20 chars in the AgentScanRequest', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['inventory:scan']);

    $response = $this->postJson('/api/inventory/agent-scan', [
        'hostname' => 'PC-T-005',
        'agent_version' => str_repeat('a', 25),
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['agent_version']);
});

it('rejects an invalid scan_status value in the AgentScanRequest', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['inventory:scan']);

    $response = $this->postJson('/api/inventory/agent-scan', [
        'hostname' => 'PC-T-006',
        'scan_status' => 'bogus',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['scan_status']);
});
