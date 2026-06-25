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
     * Browsers cannot expose the real PC hostname, so assets are identified by
     * the authenticated user_id. If the user already has an asset assigned we
     * update it; otherwise we create a new "web-scan" asset linked to them.
     *
     * @param  array<string, mixed>  $data
     */
    public function processWebScan(array $data, ?User $user = null, ?string $ip = null, ?string $userAgent = null): Asset
    {
        // Find the asset already assigned to this user, or create a new one.
        // We prefer the first active desktop/laptop asset linked to the user.
        $asset = null;

        if ($user?->id) {
            $asset = Asset::where('user_id', $user->id)
                ->whereIn('type', ['desktop', 'laptop', 'notebook', 'workstation'])
                ->latest('last_scan_at')
                ->first();
        }

        if (! $asset) {
            // Fallback: create a placeholder asset for this user
            $asset = Asset::create([
                'type' => 'desktop',
                'status' => 'active',
                'user_id' => $user?->id,
                'department_id' => $user?->department_id,
                'ip_address' => $ip,
                'last_scan_at' => now(),
                'last_scan_status' => 'web_scan',
                'registration_source' => 'scan_web',
            ]);
        }

        $updates = array_filter([
            'user_id' => $user?->id ?? $asset->user_id,
            'department_id' => $user?->department_id ?? $asset->department_id,
            'ip_address' => $ip ?? $asset->ip_address,
            'last_scan_at' => now(),
            'last_scan_status' => 'web_scan',
        ], fn ($v) => $v !== null);

        // Only overwrite hardware fields if the asset doesn't already have
        // better data from an agent scan (agent data takes priority).
        if ($asset->last_scan_status !== 'agent_scan') {
            $updates = array_merge($updates, array_filter([
                'os_name' => $data['os_name'] ?? null,
                'os_version' => $data['os_version'] ?? null,
                'cpu_cores' => $data['cpu_cores'] ?? null,
                'ram_mb' => isset($data['ram_gb']) ? (int) ($data['ram_gb'] * 1024) : null,
                'gpu_info' => $data['gpu_info'] ?? null,
            ], fn ($v) => $v !== null));
        }

        $asset->update($updates);

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

        // Sync software list (replace all, upsert batch for performance)
        if (isset($data['software']) && is_array($data['software'])) {
            $asset->software()->delete();

            $rows = [];
            $now = now()->toDateTimeString();

            foreach ($data['software'] as $sw) {
                $name = trim((string) ($sw['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                // install_date puede venir como "20241215" (YYYYMMDD sin separadores)
                // o como "2024-12-15" o vacío. Normalizamos a Y-m-d o null.
                $rawDate = trim((string) ($sw['install_date'] ?? ''));
                $installDate = null;
                if ($rawDate !== '') {
                    if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $rawDate, $m)) {
                        $installDate = "{$m[1]}-{$m[2]}-{$m[3]}";
                    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}/', $rawDate)) {
                        $installDate = substr($rawDate, 0, 10);
                    }
                    // Validar que sea una fecha real (no "00000000" u otro basura)
                    if ($installDate && ! checkdate(
                        (int) substr($installDate, 5, 2),
                        (int) substr($installDate, 8, 2),
                        (int) substr($installDate, 0, 4)
                    )) {
                        $installDate = null;
                    }
                }

                $rows[] = [
                    'asset_id' => $asset->id,
                    'name' => mb_substr($name, 0, 255),
                    'version' => mb_substr((string) ($sw['version'] ?? ''), 0, 100) ?: null,
                    'publisher' => mb_substr((string) ($sw['publisher'] ?? ''), 0, 255) ?: null,
                    'install_date' => $installDate,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Insertar en chunks de 200 para evitar límite de parámetros SQL
            foreach (array_chunk($rows, 200) as $chunk) {
                AssetSoftware::insert($chunk);
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
