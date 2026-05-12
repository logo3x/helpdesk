<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetHistory;
use App\Models\AssetScan;
use App\Models\AssetSoftware;
use App\Models\User;

/**
 * Processes inventory scan payloads from both the web browser collector
 * and the PowerShell agent. Upserts the asset record and stores a
 * history log + raw scan.
 */
class InventoryService
{
    /**
     * Process a web browser scan (limited data: UA, screen, CPU cores, RAM estimate).
     *
     * @param  array<string, mixed>  $data
     */
    public function processWebScan(array $data, ?User $user = null, ?string $ip = null, ?string $userAgent = null): Asset
    {
        $hostname = $data['hostname'] ?? $data['user_agent'] ?? 'unknown';

        $asset = Asset::findOrCreateByHostname($hostname);

        $asset->update(array_filter([
            'user_id' => $user?->id ?? $asset->user_id,
            'department_id' => $user?->department_id ?? $asset->department_id,
            'os_name' => $data['os_name'] ?? $asset->os_name,
            'os_version' => $data['os_version'] ?? $asset->os_version,
            'cpu_cores' => $data['cpu_cores'] ?? $asset->cpu_cores,
            'ram_mb' => isset($data['ram_gb']) ? (int) ($data['ram_gb'] * 1024) : $asset->ram_mb,
            'gpu_info' => $data['gpu_info'] ?? $asset->gpu_info,
            'ip_address' => $ip ?? $asset->ip_address,
            'last_scan_at' => now(),
        ], fn ($v) => $v !== null));

        AssetScan::create([
            'asset_id' => $asset->id,
            'source' => 'web_scan',
            'raw_data' => $data,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        AssetHistory::create([
            'asset_id' => $asset->id,
            'user_id' => $user?->id,
            'action' => 'scanned',
            'notes' => 'Web scan from browser',
        ]);

        return $asset;
    }

    /**
     * Process a PowerShell agent scan (full data: hardware, software, BIOS, disks).
     *
     * @param  array<string, mixed>  $data
     */
    public function processAgentScan(array $data, ?string $ip = null): Asset
    {
        $hostname = $data['hostname'] ?? 'unknown';

        $asset = Asset::findOrCreateByHostname($hostname);

        $asset->update(array_filter([
            'serial_number' => $data['serial_number'] ?? $asset->serial_number,
            'type' => $data['type'] ?? $asset->type,
            'manufacturer' => $data['manufacturer'] ?? $asset->manufacturer,
            'model' => $data['model'] ?? $asset->model,
            'os_name' => $data['os_name'] ?? $asset->os_name,
            'os_version' => $data['os_version'] ?? $asset->os_version,
            'os_architecture' => $data['os_architecture'] ?? $asset->os_architecture,
            'cpu_cores' => $data['cpu_cores'] ?? $asset->cpu_cores,
            'cpu_model' => $data['cpu_model'] ?? $asset->cpu_model,
            'ram_mb' => $data['ram_mb'] ?? $asset->ram_mb,
            'disk_total_gb' => $data['disk_total_gb'] ?? $asset->disk_total_gb,
            'gpu_info' => $data['gpu_info'] ?? $asset->gpu_info,
            'ip_address' => $ip ?? $data['ip_address'] ?? $asset->ip_address,
            'mac_address' => $data['mac_address'] ?? $asset->mac_address,
            'last_scan_at' => now(),
            // v2+: el agente reporta su propia versión y status de recolección.
            // Para PCs con agente v1 estos campos no vienen — array_filter
            // los descarta y el activo queda con su valor previo.
            'agent_version' => $data['agent_version'] ?? null,
            'last_scan_status' => $data['scan_status'] ?? null,
        ], fn ($v) => $v !== null));

        // Sync software list (replace all)
        if (isset($data['software']) && is_array($data['software'])) {
            $asset->software()->delete();

            foreach ($data['software'] as $sw) {
                AssetSoftware::create([
                    'asset_id' => $asset->id,
                    'name' => $sw['name'] ?? 'Unknown',
                    'version' => $sw['version'] ?? null,
                    'publisher' => $sw['publisher'] ?? null,
                    'install_date' => $sw['install_date'] ?? null,
                ]);
            }
        }

        AssetScan::create([
            'asset_id' => $asset->id,
            'source' => 'agent_scan',
            'raw_data' => $data,
            'ip_address' => $ip,
        ]);

        AssetHistory::create([
            'asset_id' => $asset->id,
            'action' => 'scanned',
            'notes' => 'PowerShell agent scan',
        ]);

        return $asset;
    }
}
