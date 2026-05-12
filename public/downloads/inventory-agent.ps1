<#
.SYNOPSIS
    Helpdesk Confipetrol - Agente de inventario de equipos (v2).

.DESCRIPTION
    Recolecta hardware, sistema operativo, red y software instalado del
    equipo donde se ejecuta y los envía al endpoint /api/inventory/agent-scan
    vía POST autenticado con token Sanctum.

    v2 — Mejoras de despliegue:
      - Lee el token cifrado con DPAPI (LocalMachine) en lugar de aceptar
        plain text como parámetro (más seguro).
      - Reintenta hasta 3 veces con backoff exponencial (5s, 15s, 45s) en
        fallos de red transitorios.
      - Logging local en C:\ProgramData\HelpdeskConfipetrol\agent.log con
        rotación automática (mantiene últimas 1000 líneas).
      - Envía la versión del agente en el payload para que el servidor sepa
        qué PCs todavía tienen la versión vieja.
      - Reporta status del scan (ok / partial / error) para diagnóstico.

.PARAMETER ApiUrl
    URL completa del endpoint de scan.

.PARAMETER ApiToken
    Token Sanctum (formato `id|random`). Si se omite, intenta leer el
    token cifrado de C:\ProgramData\HelpdeskConfipetrol\token.enc.

.PARAMETER DryRun
    Imprime el JSON pero no lo envía.

.EXAMPLE
    # Modo manual con token explícito:
    .\inventory-agent.ps1 -ApiUrl "https://helpdesk.confipetrol.com/api/inventory/agent-scan" -ApiToken "1|abcd..."

.EXAMPLE
    # Modo tarea programada (lee el token cifrado del disco):
    .\inventory-agent.ps1 -ApiUrl "https://helpdesk.confipetrol.com/api/inventory/agent-scan"

.NOTES
    Requiere PowerShell 5.1+. La lectura del token cifrado requiere
    ejecutarse como SYSTEM (o el mismo principal que lo cifró).
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$ApiUrl,

    [Parameter(Mandatory = $false)]
    [string]$ApiToken = '',

    [switch]$DryRun
)

$ScriptVersion = '2.0.0'
$ErrorActionPreference = 'Stop'

$InstallDir = 'C:\ProgramData\HelpdeskConfipetrol'
$LogPath = Join-Path $InstallDir 'agent.log'
$TokenEncPath = Join-Path $InstallDir 'token.enc'

# ─── Helpers ─────────────────────────────────────────────────────

function Write-Log {
    param(
        [string]$Message,
        [ValidateSet('INFO', 'WARN', 'ERROR')]
        [string]$Level = 'INFO'
    )
    $timestamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    $line = "[$timestamp] [$Level] $Message"
    Write-Host $line

    if (Test-Path $InstallDir) {
        try {
            Add-Content -Path $LogPath -Value $line -Encoding UTF8 -ErrorAction SilentlyContinue
        }
        catch {
            # No abortamos por un fallo de log — solo dejamos rastro en consola.
        }
    }
}

function Invoke-LogRotation {
    # Mantiene únicamente las últimas 1000 líneas para que agent.log
    # no crezca indefinidamente en PCs que se reinician poco.
    if (-not (Test-Path $LogPath)) { return }

    try {
        $lines = Get-Content -Path $LogPath -ErrorAction SilentlyContinue
        if ($lines.Count -gt 1000) {
            $tail = $lines | Select-Object -Last 1000
            Set-Content -Path $LogPath -Value $tail -Encoding UTF8
        }
    }
    catch {
        # Rotación es best-effort.
    }
}

function Read-EncryptedToken {
    param([string]$Path)

    if (-not (Test-Path $Path)) {
        return $null
    }

    try {
        $encBytes = [System.IO.File]::ReadAllBytes($Path)
        Add-Type -AssemblyName System.Security
        $plainBytes = [System.Security.Cryptography.ProtectedData]::Unprotect(
            $encBytes, $null, [System.Security.Cryptography.DataProtectionScope]::LocalMachine
        )
        return [System.Text.Encoding]::UTF8.GetString($plainBytes)
    }
    catch {
        Write-Log "No se pudo descifrar el token en $Path : $($_.Exception.Message)" 'ERROR'
        return $null
    }
}

function Send-ScanWithRetry {
    param(
        [string]$Url,
        [string]$Token,
        [string]$JsonPayload
    )

    $delays = @(5, 15, 45)
    $lastError = $null

    for ($attempt = 1; $attempt -le ($delays.Count + 1); $attempt++) {
        try {
            $response = Invoke-RestMethod -Uri $Url `
                -Method Post `
                -ContentType 'application/json' `
                -Headers @{
                    'Authorization' = "Bearer $Token"
                    'Accept' = 'application/json'
                    'User-Agent' = "HelpdeskConfipetrolAgent/$ScriptVersion"
                } `
                -Body $JsonPayload `
                -TimeoutSec 30

            return @{ Success = $true; Response = $response; Error = $null }
        }
        catch {
            $lastError = $_.Exception.Message
            $statusCode = $null
            if ($_.Exception.Response) {
                try { $statusCode = [int]$_.Exception.Response.StatusCode } catch {}
            }

            # 4xx (excepto 408/429) no se reintenta — es un error de cliente.
            if ($statusCode -ge 400 -and $statusCode -lt 500 -and $statusCode -ne 408 -and $statusCode -ne 429) {
                Write-Log "Error $statusCode no recuperable: $lastError" 'ERROR'
                return @{ Success = $false; Response = $null; Error = $lastError; StatusCode = $statusCode }
            }

            if ($attempt -le $delays.Count) {
                $sleep = $delays[$attempt - 1]
                Write-Log "Intento $attempt falló ($lastError). Reintentando en ${sleep}s..." 'WARN'
                Start-Sleep -Seconds $sleep
            }
        }
    }

    return @{ Success = $false; Response = $null; Error = $lastError }
}

# ─── Setup inicial ───────────────────────────────────────────────

if (Test-Path $InstallDir) {
    Invoke-LogRotation
}

Write-Log "Inventory agent v$ScriptVersion iniciando en $env:COMPUTERNAME"

# Resolver token: parámetro tiene prioridad sobre archivo cifrado.
if ([string]::IsNullOrWhiteSpace($ApiToken)) {
    $ApiToken = Read-EncryptedToken -Path $TokenEncPath
    if ([string]::IsNullOrWhiteSpace($ApiToken)) {
        Write-Log "No hay token disponible (ni en parámetro ni en $TokenEncPath). Abortando." 'ERROR'
        exit 1
    }
    Write-Log "Token leído del archivo cifrado."
}

# ─── Recolección ─────────────────────────────────────────────────

Write-Log "Recolectando datos de hardware..."

$collectionStatus = 'ok'
$collectionErrors = @()

function Get-Safely {
    param([scriptblock]$Block, [string]$What)
    try {
        return & $Block
    }
    catch {
        $script:collectionStatus = 'partial'
        $script:collectionErrors += "$What : $($_.Exception.Message)"
        Write-Log "Fallo al recolectar $What : $($_.Exception.Message)" 'WARN'
        return $null
    }
}

$cs = Get-Safely { Get-CimInstance Win32_ComputerSystem -ErrorAction Stop } 'ComputerSystem'
$bios = Get-Safely { Get-CimInstance Win32_BIOS -ErrorAction Stop } 'BIOS'
$os = Get-Safely { Get-CimInstance Win32_OperatingSystem -ErrorAction Stop } 'OperatingSystem'
$cpu = Get-Safely { Get-CimInstance Win32_Processor -ErrorAction Stop | Select-Object -First 1 } 'Processor'
$disks = Get-Safely { Get-CimInstance Win32_DiskDrive -ErrorAction Stop } 'DiskDrive'
$gpu = Get-Safely { Get-CimInstance Win32_VideoController -ErrorAction Stop | Select-Object -First 1 } 'VideoController'
$nic = Get-Safely { Get-CimInstance Win32_NetworkAdapterConfiguration -Filter 'IPEnabled = TRUE' -ErrorAction Stop | Select-Object -First 1 } 'NetworkAdapter'

$hasBattery = $null -ne (Get-Safely { Get-CimInstance Win32_Battery -ErrorAction Stop } 'Battery')
$assetType = if ($hasBattery) { 'laptop' } else { 'desktop' }

$totalDiskGb = 0
if ($disks) {
    foreach ($d in $disks) {
        if ($d.Size) { $totalDiskGb += [math]::Round($d.Size / 1GB, 0) }
    }
}

$ramMb = if ($cs -and $cs.TotalPhysicalMemory) { [math]::Round($cs.TotalPhysicalMemory / 1MB, 0) } else { $null }
$ipAddress = if ($nic -and $nic.IPAddress) { $nic.IPAddress | Where-Object { $_ -notmatch ':' } | Select-Object -First 1 } else { $null }
$macAddress = if ($nic) { $nic.MACAddress } else { $null }

Write-Log "Recolectando software instalado (registry)..."

$softwareKeys = @(
    'HKLM:\Software\Microsoft\Windows\CurrentVersion\Uninstall\*',
    'HKLM:\Software\WOW6432Node\Microsoft\Windows\CurrentVersion\Uninstall\*',
    'HKCU:\Software\Microsoft\Windows\CurrentVersion\Uninstall\*'
)

$softwareList = @()
foreach ($key in $softwareKeys) {
    try {
        Get-ItemProperty $key -ErrorAction SilentlyContinue |
            Where-Object { $_.DisplayName -and $_.DisplayName.Trim() -ne '' } |
            ForEach-Object {
                $softwareList += [PSCustomObject]@{
                    name = $_.DisplayName
                    version = if ($_.DisplayVersion) { $_.DisplayVersion } else { $null }
                    publisher = if ($_.Publisher) { $_.Publisher } else { $null }
                    install_date = if ($_.InstallDate -and $_.InstallDate -match '^\d{8}$') {
                        "$($_.InstallDate.Substring(0,4))-$($_.InstallDate.Substring(4,2))-$($_.InstallDate.Substring(6,2))"
                    } else {
                        $null
                    }
                }
            }
    }
    catch {
        $collectionStatus = 'partial'
        $collectionErrors += "SoftwareRegistry($key) : $($_.Exception.Message)"
    }
}

$softwareList = $softwareList | Sort-Object name, version -Unique

# ─── Construcción del payload ───────────────────────────────────

$payload = @{
    hostname = $env:COMPUTERNAME
    serial_number = if ($bios) { $bios.SerialNumber } else { $null }
    type = $assetType
    manufacturer = if ($cs) { $cs.Manufacturer } else { $null }
    model = if ($cs) { $cs.Model } else { $null }
    os_name = if ($os) { $os.Caption } else { $null }
    os_version = if ($os) { $os.Version } else { $null }
    os_architecture = if ($os) { $os.OSArchitecture } else { $null }
    cpu_cores = if ($cpu) { $cpu.NumberOfCores } else { $null }
    cpu_model = if ($cpu) { $cpu.Name } else { $null }
    ram_mb = $ramMb
    disk_total_gb = $totalDiskGb
    gpu_info = if ($gpu) { $gpu.Name } else { $null }
    ip_address = $ipAddress
    mac_address = $macAddress
    software = @($softwareList)
    scanned_at = (Get-Date).ToString('o')
    agent_version = $ScriptVersion
    scan_status = $collectionStatus
}

$json = $payload | ConvertTo-Json -Depth 5 -Compress

Write-Log "Payload listo: $($payload.hostname) · CPU=$($payload.cpu_model) · RAM=$($payload.ram_mb)MB · $($softwareList.Count) programas · status=$collectionStatus"

if ($DryRun) {
    Write-Log "DryRun activo. JSON que se enviaría:" 'WARN'
    Write-Output $json
    exit 0
}

# ─── Envío al endpoint ──────────────────────────────────────────

Write-Log "Enviando a $ApiUrl ..."

$result = Send-ScanWithRetry -Url $ApiUrl -Token $ApiToken -JsonPayload $json

if ($result.Success) {
    $assetId = if ($result.Response.id) { $result.Response.id } else { 'N/A' }
    Write-Log "Scan registrado correctamente. Asset ID: $assetId · agent_version=$ScriptVersion · status=$collectionStatus"
    exit 0
}
else {
    Write-Log "Scan FALLÓ tras todos los reintentos: $($result.Error)" 'ERROR'
    if ($collectionErrors.Count -gt 0) {
        Write-Log "Errores de recolección previos: $($collectionErrors -join '; ')" 'ERROR'
    }
    exit 2
}
