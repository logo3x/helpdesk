<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ScannerController extends Controller
{
    /**
     * GET /api/inventory/scanner/download
     * Genera y descarga scanconfi.bat — lanzador que abre PowerShell con
     * bypass de ejecución y corre el script incrustado. Doble clic funciona.
     */
    public function download(Request $request): Response
    {
        $user = $request->user();
        $serverUrl = rtrim(config('app.url'), '/');
        $email = $user->email;
        $url = $serverUrl.'/api/inventory/scanner-scan';

        // Generamos el .ps1 incrustado como string escapado dentro del .bat
        $ps1 = $this->buildScript($serverUrl, $url, $email);

        // El .bat extrae el script a un temp y lo ejecuta con bypass
        // PowerShell -EncodedCommand acepta Base64 del script completo
        $encoded = base64_encode(mb_convert_encoding($ps1, 'UTF-16LE', 'UTF-8'));

        $bat = "@echo off\r\n";
        $bat .= "title ScanConfi - Helpdesk Confipetrol\r\n";
        $bat .= 'PowerShell.exe -NoProfile -ExecutionPolicy Bypass -EncodedCommand '.$encoded."\r\n";
        $bat .= "pause\r\n";

        return response($bat, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="scanconfi.bat"',
            'Content-Length' => strlen($bat),
        ]);
    }

    /**
     * POST /api/inventory/scanner-verify
     * Valida email + password antes de que el script capture hardware.
     * Responde 200 OK con el nombre del agente, o 401/403 si falla.
     */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Credenciales incorrectas.'], 401);
        }

        if (! $user->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte', 'agente_soporte'])) {
            return response()->json(['message' => 'Sin permiso para registrar inventario.'], 403);
        }

        return response()->json(['ok' => true, 'name' => $user->name]);
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

        try {
            $asset = app(InventoryService::class)->processAgentScan(
                data: array_merge($data, ['scan_status' => 'agent_scan']),
                ip: $request->ip(),
            );
        } catch (\Throwable $e) {
            Log::error('ScannerController@scan error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'email' => $data['email'],
                'hostname' => $data['hostname'] ?? null,
                'software_count' => count($data['software'] ?? []),
            ]);

            return response()->json([
                'message' => 'Error al procesar el scan.',
                'detail' => $e->getMessage(),
            ], 500);
        }

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
        $lines = [];

        // ── Encabezado ────────────────────────────────────────────────────────
        $lines[] = '# ScanConfi v1.0 — Helpdesk Confipetrol';
        $lines[] = '# Agente : '.$email;
        $lines[] = '# Server : '.$serverUrl;
        $lines[] = '# Uso    : Doble clic o ejecutar desde PowerShell con .\scanconfi.ps1';
        $lines[] = '';
        $lines[] = '# Política de ejecución: permitir este script sin cambiar la config global';
        $lines[] = 'Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass -Force 2>$null';
        $lines[] = '';
        $lines[] = '$ErrorActionPreference = \'SilentlyContinue\'';
        $lines[] = '$Email     = \''.$email.'\'';
        $lines[] = '$ApiUrl    = \''.$url.'\'';
        $lines[] = '$VerifyUrl = \''.$serverUrl.'/api/inventory/scanner-verify\'';
        $lines[] = '';
        $lines[] = '# Detectar si fue lanzado con doble clic (sin consola padre)';
        $lines[] = '# En ese caso la ventana se cierra sola al terminar — la mantenemos abierta';
        $lines[] = '$DoubleClicked = ($Host.Name -eq "ConsoleHost") -and';
        $lines[] = '    ([System.Diagnostics.Process]::GetCurrentProcess().MainWindowTitle -ne "")  -and';
        $lines[] = '    ($null -eq $MyInvocation.PSCommandPath -or $MyInvocation.PSCommandPath -eq "")';
        $lines[] = '';

        // ── Función de utilidad: step con número ──────────────────────────────
        $lines[] = 'function Show-Step($n, $total, $text) {';
        $lines[] = '    $bar = "[" + ("=" * $n) + (" " * ($total - $n)) + "]"';
        $lines[] = '    Write-Host ("  " + $bar + " Paso " + $n + "/" + $total + "  " + $text) -ForegroundColor Cyan';
        $lines[] = '}';
        $lines[] = 'function Show-Ok($msg)    { Write-Host ("    OK  " + $msg) -ForegroundColor Green }';
        $lines[] = 'function Show-Info($msg)  { Write-Host ("    >>  " + $msg) -ForegroundColor Gray }';
        $lines[] = 'function Show-Error($msg) { Write-Host ("    XX  " + $msg) -ForegroundColor Red }';
        $lines[] = 'function Show-Sep { Write-Host "  " + ("-" * 54) -ForegroundColor DarkGray }';
        $lines[] = '';

        // ── Banner ────────────────────────────────────────────────────────────
        $lines[] = 'Clear-Host';
        $lines[] = 'Write-Host ""';
        $lines[] = 'Write-Host "  ╔══════════════════════════════════════════════════╗" -ForegroundColor DarkCyan';
        $lines[] = 'Write-Host "  ║        SCANCONFI  -  Inventario de Equipos       ║" -ForegroundColor Cyan';
        $lines[] = 'Write-Host "  ║              Helpdesk Confipetrol v1.0           ║" -ForegroundColor DarkCyan';
        $lines[] = 'Write-Host "  ╚══════════════════════════════════════════════════╝" -ForegroundColor DarkCyan';
        $lines[] = 'Write-Host ""';
        $lines[] = 'Show-Info ("Agente  : " + $Email)';
        $lines[] = 'Show-Info ("Equipo  : " + $env:COMPUTERNAME)';
        $lines[] = 'Show-Info ("Fecha   : " + (Get-Date -Format "dd/MM/yyyy HH:mm"))';
        $lines[] = 'Write-Host ""';

        // ── Paso 1: Autenticacion ─────────────────────────────────────────────
        $lines[] = 'Show-Step 1 4 "Autenticacion"';
        $lines[] = 'Show-Sep';
        $lines[] = '$SecurePass = Read-Host "    Contrasena Helpdesk" -AsSecureString';
        $lines[] = '$Password   = [System.Runtime.InteropServices.Marshal]::PtrToStringAuto(';
        $lines[] = '                [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($SecurePass))';
        $lines[] = '';
        $lines[] = '# Verificar credenciales antes de capturar hardware';
        $lines[] = 'Write-Host "    Verificando credenciales..." -ForegroundColor DarkGray';
        $lines[] = 'try {';
        $lines[] = '    $VBytes  = [System.Text.Encoding]::UTF8.GetBytes(("{""email"":""$Email"",""password"":""$Password""}"))';
        $lines[] = '    $VResult = Invoke-RestMethod -Uri $VerifyUrl -Method POST -Body $VBytes `';
        $lines[] = '               -ContentType "application/json; charset=utf-8" `';
        $lines[] = '               -Headers @{ Accept = "application/json" }';
        $lines[] = '    Show-Ok ("Autenticado como: " + $VResult.name)';
        $lines[] = '} catch {';
        $lines[] = '    $VCode = $_.Exception.Response.StatusCode.value__';
        $lines[] = '    Write-Host ""';
        $lines[] = '    if ($VCode -eq 401) { Show-Error "Contrasena incorrecta. Verifica tu clave del Helpdesk." }';
        $lines[] = '    elseif ($VCode -eq 403) { Show-Error "Tu usuario no tiene permiso para registrar inventario." }';
        $lines[] = '    else { Show-Error ("Error al verificar: " + $_.Exception.Message) }';
        $lines[] = '    Write-Host ""';
        $lines[] = '    Write-Host "  Presiona Enter para cerrar..." -ForegroundColor DarkGray';
        $lines[] = '    [void][System.Console]::ReadLine()';
        $lines[] = '    exit 1';
        $lines[] = '}';
        $lines[] = 'Write-Host ""';

        // ── Paso 2: Hardware ──────────────────────────────────────────────────
        $lines[] = 'Show-Step 2 4 "Capturando hardware del equipo"';
        $lines[] = 'Show-Sep';
        $lines[] = '';
        $lines[] = 'Write-Host "    Leyendo sistema..." -ForegroundColor DarkGray';
        $lines[] = '$CS   = Get-CimInstance Win32_ComputerSystem';
        $lines[] = '$OS   = Get-CimInstance Win32_OperatingSystem';
        $lines[] = '$CPU  = Get-CimInstance Win32_Processor | Select-Object -First 1';
        $lines[] = '$BIOS = Get-CimInstance Win32_BIOS';
        $lines[] = '$GPU  = (Get-CimInstance Win32_VideoController | Select-Object -First 1).Name';
        $lines[] = '$Enc  = Get-CimInstance Win32_SystemEnclosure';
        $lines[] = '';
        $lines[] = '$RamMb  = [math]::Round($CS.TotalPhysicalMemory / 1MB)';
        $lines[] = '$RamGb  = [math]::Round($RamMb / 1024)';
        $lines[] = '$Disk   = Get-CimInstance Win32_DiskDrive | Sort-Object Size -Descending | Select-Object -First 1';
        $lines[] = '$DiskGb = if ($Disk) { [math]::Round($Disk.Size / 1GB) } else { $null }';
        $lines[] = '$Net    = Get-CimInstance Win32_NetworkAdapterConfiguration |';
        $lines[] = '          Where-Object { $_.IPEnabled -and $_.IPAddress -and $_.MACAddress } |';
        $lines[] = '          Select-Object -First 1';
        $lines[] = '$IpAddress  = if ($Net) { $Net.IPAddress[0] } else { $null }';
        $lines[] = '$MacAddress = if ($Net) { $Net.MACAddress } else { $null }';
        $lines[] = '';
        $lines[] = '$ChassisMap = @{1="other";2="desktop";3="desktop";4="desktop";5="desktop";';
        $lines[] = '  6="desktop";7="desktop";8="laptop";9="laptop";10="laptop";11="laptop";';
        $lines[] = '  12="laptop";13="desktop";14="desktop";15="desktop";16="desktop";';
        $lines[] = '  17="server";18="server";19="server";20="server";21="desktop";22="desktop";23="server"}';
        $lines[] = '$ChassisType = $Enc.ChassisTypes | Select-Object -First 1';
        $lines[] = '$AssetType   = if ($ChassisMap.ContainsKey([int]$ChassisType)) { $ChassisMap[[int]$ChassisType] } else { "desktop" }';
        $lines[] = '';
        $lines[] = '$OsName    = $OS.Caption -replace "Microsoft ", ""';
        $lines[] = '$OsVersion = $OS.Version';
        $lines[] = '$OsArch    = $OS.OSArchitecture';
        $lines[] = '';
        $lines[] = 'Show-Ok ("Equipo  : " + $CS.Manufacturer + " " + $CS.Model)';
        $lines[] = 'Show-Ok ("SO      : " + $OsName + " " + $OsArch)';
        $lines[] = 'Show-Ok ("CPU     : " + $CPU.Name + " (" + $CS.NumberOfLogicalProcessors + " cores)")';
        $lines[] = 'Show-Ok ("RAM     : " + $RamGb + " GB  |  Disco: " + $DiskGb + " GB")';
        $lines[] = 'Show-Ok ("IP/MAC  : " + $IpAddress + " / " + $MacAddress)';
        $lines[] = 'Write-Host ""';

        // ── Paso 3: Software ──────────────────────────────────────────────────
        $lines[] = 'Show-Step 3 4 "Capturando software instalado"';
        $lines[] = 'Show-Sep';
        $lines[] = 'Write-Host "    Leyendo registro de Windows..." -ForegroundColor DarkGray';
        $lines[] = '$SoftwareRaw = Get-ItemProperty `';
        $lines[] = '    "HKLM:\Software\Microsoft\Windows\CurrentVersion\Uninstall\*",`';
        $lines[] = '    "HKLM:\Software\Wow6432Node\Microsoft\Windows\CurrentVersion\Uninstall\*" `';
        $lines[] = '    -ErrorAction SilentlyContinue |';
        $lines[] = '    Where-Object { $_.DisplayName -and $_.DisplayName.Trim() -ne "" } |';
        $lines[] = '    Select-Object DisplayName, DisplayVersion, Publisher, InstallDate |';
        $lines[] = '    Sort-Object DisplayName -Unique';
        $lines[] = '$Software = @($SoftwareRaw | ForEach-Object {';
        $lines[] = '    @{ name=$_.DisplayName; version=$_.DisplayVersion; publisher=$_.Publisher; install_date=$_.InstallDate }';
        $lines[] = '})';
        $lines[] = 'Show-Ok ($Software.Count.ToString() + " programas encontrados")';
        $lines[] = 'Write-Host ""';

        // ── Datos administrativos ─────────────────────────────────────────────
        $lines[] = 'Show-Step 4 4 "Datos adicionales del activo (opcional)"';
        $lines[] = 'Show-Sep';
        $lines[] = 'Write-Host "    Presiona Enter para omitir cada campo" -ForegroundColor DarkGray';
        $lines[] = 'Write-Host ""';
        $lines[] = '$CustodianName  = (Read-Host "    Nombre del custodio   ").Trim()';
        $lines[] = '$Field          = (Read-Host "    Campo operativo        ").Trim()';
        $lines[] = '$LocationZone   = (Read-Host "    Ubicacion / Zona       ").Trim()';
        $lines[] = '$ManagementArea = (Read-Host "    Gerencia               ").Trim()';
        $lines[] = '$AssetTag       = (Read-Host "    TAG / Etiqueta         ").Trim()';
        $lines[] = '$Notes          = (Read-Host "    Notas                  ").Trim()';
        $lines[] = 'Write-Host ""';

        // ── Envío ─────────────────────────────────────────────────────────────
        $lines[] = 'Write-Host "  Enviando datos al servidor..." -ForegroundColor Yellow';
        $lines[] = 'Show-Sep';
        $lines[] = '';
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
        $lines[] = 'if ($CustodianName  -ne "") { $Payload.custodian_name  = $CustodianName }';
        $lines[] = 'if ($Field          -ne "") { $Payload.field           = $Field }';
        $lines[] = 'if ($LocationZone   -ne "") { $Payload.location_zone   = $LocationZone }';
        $lines[] = 'if ($ManagementArea -ne "") { $Payload.management_area = $ManagementArea }';
        $lines[] = 'if ($AssetTag       -ne "") { $Payload.asset_tag       = $AssetTag }';
        $lines[] = 'if ($Notes          -ne "") { $Payload.notes           = $Notes }';
        $lines[] = '';
        // Usamos Invoke-WebRequest en lugar de Invoke-RestMethod para poder
        // leer el body de la respuesta en errores 4xx/5xx (PS 5.1 no expone
        // el stream en la excepción de Invoke-RestMethod).
        $lines[] = '$Json  = $Payload | ConvertTo-Json -Depth 5 -Compress';
        $lines[] = '$Bytes = [System.Text.Encoding]::UTF8.GetBytes($Json)';
        $lines[] = '';
        $lines[] = 'try {';
        $lines[] = '    $WebResp  = Invoke-WebRequest -Uri $ApiUrl -Method POST -Body $Bytes `';
        $lines[] = '                -ContentType "application/json; charset=utf-8" `';
        $lines[] = '                -Headers @{ Accept = "application/json" } `';
        $lines[] = '                -UseBasicParsing -ErrorAction Stop';
        $lines[] = '    $Response = $WebResp.Content | ConvertFrom-Json';
        $lines[] = '';
        $lines[] = '    Write-Host ""';
        $lines[] = '    Write-Host "  ╔══════════════════════════════════════════════════╗" -ForegroundColor Green';
        $lines[] = '    Write-Host "  ║   EQUIPO REGISTRADO CORRECTAMENTE               ║" -ForegroundColor Green';
        $lines[] = '    Write-Host "  ╚══════════════════════════════════════════════════╝" -ForegroundColor Green';
        $lines[] = '    Write-Host ""';
        $lines[] = '    Show-Ok ("ID Activo  : " + $Response.asset_id)';
        $lines[] = '    Show-Ok ("Hostname   : " + $env:COMPUTERNAME)';
        $lines[] = '    Show-Ok ("Registrado : " + $Response.scanned_by)';
        $lines[] = '    Show-Ok ("Software   : " + $Software.Count + " programas registrados")';
        $lines[] = '}';
        $lines[] = 'catch {';
        $lines[] = '    Write-Host ""';
        $lines[] = '    Write-Host "  ╔══════════════════════════════════════════════════╗" -ForegroundColor Red';
        $lines[] = '    Write-Host "  ║   ERROR AL ENVIAR DATOS                         ║" -ForegroundColor Red';
        $lines[] = '    Write-Host "  ╚══════════════════════════════════════════════════╝" -ForegroundColor Red';
        $lines[] = '    Write-Host ""';
        $lines[] = '    # Invoke-WebRequest sí expone el body en el error';
        $lines[] = '    $ErrBody = ""';
        $lines[] = '    if ($_.Exception.Response) {';
        $lines[] = '        $StatusCode = [int]$_.Exception.Response.StatusCode';
        $lines[] = '        try { $ErrBody = $_.ErrorDetails.Message } catch {}';
        $lines[] = '    } else { $StatusCode = 0 }';
        $lines[] = '    if ($StatusCode -eq 401) {';
        $lines[] = '        Show-Error "Credenciales incorrectas. Verifica tu contrasena Helpdesk."';
        $lines[] = '    } elseif ($StatusCode -eq 403) {';
        $lines[] = '        Show-Error "Sin permiso. Tu usuario no tiene acceso a inventario."';
        $lines[] = '    } elseif ($StatusCode -eq 422) {';
        $lines[] = '        Show-Error "Error de validacion."';
        $lines[] = '        if ($ErrBody) { Show-Error $ErrBody }';
        $lines[] = '    } else {';
        $lines[] = '        Show-Error ("Error " + $StatusCode + ": " + $_.Exception.Message)';
        $lines[] = '        if ($ErrBody) { Show-Error $ErrBody }';
        $lines[] = '    }';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = 'Write-Host ""';
        $lines[] = 'Write-Host "  Presiona Enter para cerrar..." -ForegroundColor DarkGray';
        $lines[] = '[void][System.Console]::ReadLine()';

        return implode("\r\n", $lines);
    }
}
