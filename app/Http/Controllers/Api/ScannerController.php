<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ScannerController extends Controller
{
    /**
     * GET /soporte/scanner/download
     * Genera y descarga el script scanconfi.ps1 con el email del técnico incrustado.
     */
    public function download(Request $request): Response
    {
        $user = $request->user();
        $serverUrl = rtrim(config('app.url'), '/');
        $email = $user->email;
        $url = $serverUrl.'/api/inventory/scanner-scan';

        $ps1 = $this->buildScript($serverUrl, $url, $email);

        return response($ps1, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="scanconfi.ps1"',
            'Content-Length' => strlen($ps1),
        ]);
    }

    /**
     * POST /api/inventory/scanner-scan
     * Recibe el payload del scanconfi.ps1. Autentica con email + password.
     */
    public function scan(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:50'],
            'os_name' => ['nullable', 'string', 'max:255'],
            'os_version' => ['nullable', 'string', 'max:255'],
            'os_architecture' => ['nullable', 'string', 'max:20'],
            'cpu_cores' => ['nullable', 'integer'],
            'cpu_model' => ['nullable', 'string', 'max:255'],
            'ram_mb' => ['nullable', 'integer'],
            'disk_total_gb' => ['nullable', 'integer'],
            'gpu_info' => ['nullable', 'string', 'max:500'],
            'ip_address' => ['nullable', 'string', 'max:45'],
            'mac_address' => ['nullable', 'string', 'max:17'],
            'agent_version' => ['nullable', 'string', 'max:20'],
            'software' => ['nullable', 'array'],
            'software.*.name' => ['required', 'string', 'max:255'],
            'software.*.version' => ['nullable', 'string', 'max:100'],
            'software.*.publisher' => ['nullable', 'string', 'max:255'],
            'software.*.install_date' => ['nullable', 'string', 'max:20'],
            'custodian_name' => ['nullable', 'string', 'max:150'],
            'field' => ['nullable', 'string', 'max:100'],
            'location_zone' => ['nullable', 'string', 'max:100'],
            'management_area' => ['nullable', 'string', 'max:120'],
            'asset_tag' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos.', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Credenciales incorrectas.'], 401);
        }

        if (! $user->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte', 'agente_soporte'])) {
            return response()->json(['message' => 'Sin permiso para registrar inventario.'], 403);
        }

        $asset = app(InventoryService::class)->processAgentScan(
            data: array_merge($data, ['scan_status' => 'agent_scan']),
            ip: $request->ip(),
        );

        $adminFields = array_filter([
            'custodian_name' => $data['custodian_name'] ?? null,
            'field' => $data['field'] ?? null,
            'location_zone' => $data['location_zone'] ?? null,
            'management_area' => $data['management_area'] ?? null,
            'asset_tag' => $data['asset_tag'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        if (! empty($adminFields)) {
            $asset->update($adminFields);
        }

        return response()->json([
            'message' => 'Equipo registrado correctamente.',
            'asset_id' => $asset->id,
            'hostname' => $asset->hostname,
            'scanned_by' => $user->name,
        ]);
    }

    private function buildScript(string $serverUrl, string $url, string $email): string
    {
        // Usamos concatenación para evitar que PHP interpole las variables PowerShell ($var)
        $lines = [];
        $lines[] = '# ─────────────────────────────────────────────────────────────────────────';
        $lines[] = '#  ScanConfi — Escaner de inventario Helpdesk Confipetrol';
        $lines[] = '#  Generado para: '.$email;
        $lines[] = '#  Servidor: '.$serverUrl;
        $lines[] = '# ─────────────────────────────────────────────────────────────────────────';
        $lines[] = '#  Ejecucion: .\scanconfi.ps1';
        $lines[] = '#  Requisito: PowerShell 5+ (incluido en Windows 10/11)';
        $lines[] = '# ─────────────────────────────────────────────────────────────────────────';
        $lines[] = '';
        $lines[] = '$ErrorActionPreference = \'SilentlyContinue\'';
        $lines[] = '$Email  = \''.$email.'\'';
        $lines[] = '$ApiUrl = \''.$url.'\'';
        $lines[] = '';
        $lines[] = 'Write-Host ""';
        $lines[] = 'Write-Host "  Helpdesk Confipetrol - Escaner de Inventario" -ForegroundColor Cyan';
        $lines[] = 'Write-Host "  ─────────────────────────────────────────────" -ForegroundColor DarkGray';
        $lines[] = 'Write-Host ("  Agente  : " + $Email) -ForegroundColor Gray';
        $lines[] = 'Write-Host ""';
        $lines[] = '';
        $lines[] = '# Contrasena';
        $lines[] = '$SecurePass = Read-Host "  Contrasena" -AsSecureString';
        $lines[] = '$Password   = [System.Runtime.InteropServices.Marshal]::PtrToStringAuto(';
        $lines[] = '                [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($SecurePass))';
        $lines[] = '';
        $lines[] = 'Write-Host ""';
        $lines[] = 'Write-Host "  [Capturando hardware...]" -ForegroundColor Yellow -NoNewline';
        $lines[] = '';
        $lines[] = '# Hardware';
        $lines[] = '$CS   = Get-CimInstance Win32_ComputerSystem';
        $lines[] = '$OS   = Get-CimInstance Win32_OperatingSystem';
        $lines[] = '$CPU  = Get-CimInstance Win32_Processor | Select-Object -First 1';
        $lines[] = '$BIOS = Get-CimInstance Win32_BIOS';
        $lines[] = '$GPU  = (Get-CimInstance Win32_VideoController | Select-Object -First 1).Name';
        $lines[] = '$RamMb = [math]::Round($CS.TotalPhysicalMemory / 1MB)';
        $lines[] = '$Disk = Get-CimInstance Win32_DiskDrive | Sort-Object Size -Descending | Select-Object -First 1';
        $lines[] = '$DiskGb = if ($Disk) { [math]::Round($Disk.Size / 1GB) } else { $null }';
        $lines[] = '$Net = Get-CimInstance Win32_NetworkAdapterConfiguration |';
        $lines[] = '        Where-Object { $_.IPEnabled -and $_.IPAddress -and $_.MACAddress } |';
        $lines[] = '        Select-Object -First 1';
        $lines[] = '$IpAddress  = if ($Net) { $Net.IPAddress[0] } else { $null }';
        $lines[] = '$MacAddress = if ($Net) { $Net.MACAddress } else { $null }';
        $lines[] = '';
        $lines[] = '$ChassisMap = @{ 1="other";2="desktop";3="desktop";4="desktop";5="desktop";';
        $lines[] = '  6="desktop";7="desktop";8="laptop";9="laptop";10="laptop";11="laptop";';
        $lines[] = '  12="laptop";13="desktop";14="desktop";15="desktop";16="desktop";';
        $lines[] = '  17="server";18="server";19="server";20="server";21="desktop";22="desktop";';
        $lines[] = '  23="server" }';
        $lines[] = '$ChassisType = (Get-CimInstance Win32_SystemEnclosure).ChassisTypes | Select-Object -First 1';
        $lines[] = '$AssetType   = if ($ChassisMap.ContainsKey([int]$ChassisType)) { $ChassisMap[[int]$ChassisType] } else { "desktop" }';
        $lines[] = '';
        $lines[] = '$OsName    = $OS.Caption -replace "Microsoft ", ""';
        $lines[] = '$OsVersion = $OS.Version';
        $lines[] = '$OsArch    = $OS.OSArchitecture';
        $lines[] = '';
        $lines[] = 'Write-Host " OK" -ForegroundColor Green';
        $lines[] = '';
        $lines[] = '# Software instalado';
        $lines[] = 'Write-Host "  [Capturando software...]" -ForegroundColor Yellow -NoNewline';
        $lines[] = '$SoftwareRaw = Get-ItemProperty `';
        $lines[] = '    "HKLM:\Software\Microsoft\Windows\CurrentVersion\Uninstall\*",`';
        $lines[] = '    "HKLM:\Software\Wow6432Node\Microsoft\Windows\CurrentVersion\Uninstall\*" `';
        $lines[] = '    -ErrorAction SilentlyContinue |';
        $lines[] = '    Where-Object { $_.DisplayName -and $_.DisplayName.Trim() -ne "" } |';
        $lines[] = '    Select-Object DisplayName, DisplayVersion, Publisher, InstallDate |';
        $lines[] = '    Sort-Object DisplayName -Unique';
        $lines[] = '';
        $lines[] = '$Software = @($SoftwareRaw | ForEach-Object {';
        $lines[] = '    @{';
        $lines[] = '        name         = $_.DisplayName';
        $lines[] = '        version      = $_.DisplayVersion';
        $lines[] = '        publisher    = $_.Publisher';
        $lines[] = '        install_date = $_.InstallDate';
        $lines[] = '    }';
        $lines[] = '})';
        $lines[] = 'Write-Host (" OK (" + $Software.Count + " programas)") -ForegroundColor Green';
        $lines[] = '';
        $lines[] = '# Datos administrativos opcionales';
        $lines[] = 'Write-Host ""';
        $lines[] = 'Write-Host "  Datos del activo (Enter para omitir)" -ForegroundColor Cyan';
        $lines[] = 'Write-Host "  ─────────────────────────────────────" -ForegroundColor DarkGray';
        $lines[] = '$CustodianName  = (Read-Host "  Nombre del custodio  ").Trim()';
        $lines[] = '$Field          = (Read-Host "  Campo operativo      ").Trim()';
        $lines[] = '$LocationZone   = (Read-Host "  Ubicacion / Zona     ").Trim()';
        $lines[] = '$ManagementArea = (Read-Host "  Gerencia             ").Trim()';
        $lines[] = '$AssetTag       = (Read-Host "  TAG / Etiqueta       ").Trim()';
        $lines[] = '$Notes          = (Read-Host "  Notas                ").Trim()';
        $lines[] = '';
        $lines[] = '# Payload base';
        $lines[] = '$Payload = @{';
        $lines[] = '    email           = $Email';
        $lines[] = '    password        = $Password';
        $lines[] = '    hostname        = $env:COMPUTERNAME';
        $lines[] = '    serial_number   = $BIOS.SerialNumber';
        $lines[] = '    manufacturer    = $CS.Manufacturer';
        $lines[] = '    model           = $CS.Model';
        $lines[] = '    type            = $AssetType';
        $lines[] = '    os_name         = $OsName';
        $lines[] = '    os_version      = $OsVersion';
        $lines[] = '    os_architecture = $OsArch';
        $lines[] = '    cpu_cores       = $CS.NumberOfLogicalProcessors';
        $lines[] = '    cpu_model       = $CPU.Name';
        $lines[] = '    ram_mb          = $RamMb';
        $lines[] = '    disk_total_gb   = $DiskGb';
        $lines[] = '    gpu_info        = $GPU';
        $lines[] = '    ip_address      = $IpAddress';
        $lines[] = '    mac_address     = $MacAddress';
        $lines[] = '    agent_version   = "scanconfi-1.0"';
        $lines[] = '    software        = $Software';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = '# Agregar opcionales solo si se ingresaron';
        $lines[] = 'if ($CustodianName  -ne "") { $Payload.custodian_name  = $CustodianName }';
        $lines[] = 'if ($Field          -ne "") { $Payload.field           = $Field }';
        $lines[] = 'if ($LocationZone   -ne "") { $Payload.location_zone   = $LocationZone }';
        $lines[] = 'if ($ManagementArea -ne "") { $Payload.management_area = $ManagementArea }';
        $lines[] = 'if ($AssetTag       -ne "") { $Payload.asset_tag       = $AssetTag }';
        $lines[] = 'if ($Notes          -ne "") { $Payload.notes           = $Notes }';
        $lines[] = '';
        $lines[] = '# Envío';
        $lines[] = 'Write-Host ""';
        $lines[] = 'Write-Host "  [Enviando datos al servidor...]" -ForegroundColor Yellow -NoNewline';
        $lines[] = '';
        $lines[] = 'try {';
        $lines[] = '    $Json     = $Payload | ConvertTo-Json -Depth 5 -Compress';
        $lines[] = '    $Bytes    = [System.Text.Encoding]::UTF8.GetBytes($Json)';
        $lines[] = '    $Response = Invoke-RestMethod `';
        $lines[] = '        -Uri $ApiUrl `';
        $lines[] = '        -Method POST `';
        $lines[] = '        -Body $Bytes `';
        $lines[] = '        -ContentType "application/json; charset=utf-8" `';
        $lines[] = '        -Headers @{ Accept = "application/json" }';
        $lines[] = '';
        $lines[] = '    Write-Host " OK" -ForegroundColor Green';
        $lines[] = '    Write-Host ""';
        $lines[] = '    Write-Host ("  OK " + $Response.message) -ForegroundColor Green';
        $lines[] = '    Write-Host ("  ID Activo  : " + $Response.asset_id) -ForegroundColor White';
        $lines[] = '    Write-Host ("  Hostname   : " + $env:COMPUTERNAME) -ForegroundColor White';
        $lines[] = '    Write-Host ("  Registrado : " + $Response.scanned_by) -ForegroundColor Gray';
        $lines[] = '}';
        $lines[] = 'catch {';
        $lines[] = '    Write-Host " ERROR" -ForegroundColor Red';
        $lines[] = '    Write-Host ""';
        $lines[] = '    $StatusCode = $_.Exception.Response.StatusCode.value__';
        $lines[] = '    if ($StatusCode -eq 401) {';
        $lines[] = '        Write-Host "  X Credenciales incorrectas. Verifica tu contrasena." -ForegroundColor Red';
        $lines[] = '    } elseif ($StatusCode -eq 403) {';
        $lines[] = '        Write-Host "  X Sin permiso. Contacta al administrador." -ForegroundColor Red';
        $lines[] = '    } else {';
        $lines[] = '        Write-Host ("  X Error: " + $_.Exception.Message) -ForegroundColor Red';
        $lines[] = '    }';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = 'Write-Host ""';
        $lines[] = 'Write-Host "  Presiona Enter para cerrar..." -ForegroundColor DarkGray';
        $lines[] = 'Read-Host | Out-Null';

        return implode("\r\n", $lines);
    }
}
