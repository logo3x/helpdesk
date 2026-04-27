<#
.SYNOPSIS
    Helpdesk Confipetrol - Agente de inventario de equipos.

.DESCRIPTION
    Recolecta hardware, sistema operativo, red y software instalado
    del equipo donde se ejecuta y los envía al endpoint del helpdesk
    /api/inventory/agent-scan vía POST autenticado con token Sanctum.

    Pensado para ser instalado por IT en cada equipo corporativo y
    programado vía Tarea Programada de Windows (Task Scheduler) para
    ejecutarse semanalmente o al iniciar sesión.

.PARAMETER ApiUrl
    URL completa del endpoint de scan. Ej: https://helpdesk.confipetrol.com/api/inventory/agent-scan

.PARAMETER ApiToken
    Token Sanctum personal con ability 'inventory:scan'. Generar desde
    /admin → Usuarios → Tokens → Crear (tokenCan('inventory:scan')).

.EXAMPLE
    .\inventory-agent.ps1 -ApiUrl "https://helpdesk.confipetrol.com/api/inventory/agent-scan" -ApiToken "1|abcd..."

.EXAMPLE
    # Programar como tarea de Windows ejecutándose cada lunes 9 AM:
    schtasks /Create /SC WEEKLY /D MON /TN "Helpdesk Inventory Scan" /TR `
      "powershell.exe -NoProfile -ExecutionPolicy Bypass -File C:\Tools\inventory-agent.ps1 -ApiUrl '...' -ApiToken '...'" /ST 09:00

.NOTES
    Requiere PowerShell 5.1+ (incluido en Windows 10/11). No requiere
    privilegios elevados — todos los datos vienen de CIM/WMI con el
    usuario actual. Si quieres incluir BIOS y serial: ejecutar como admin.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$ApiUrl,

    [Parameter(Mandatory = $true)]
    [string]$ApiToken,

    [switch]$DryRun  # Si está presente, imprime el JSON pero no envía.
)

$ErrorActionPreference = 'Stop'

function Write-Log {
    param([string]$Message, [string]$Level = 'INFO')
    $timestamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    Write-Host "[$timestamp] [$Level] $Message"
}

# ─── Recolección ─────────────────────────────────────────────────

Write-Log "Recolectando datos de hardware..."

$cs = Get-CimInstance Win32_ComputerSystem -ErrorAction SilentlyContinue
$bios = Get-CimInstance Win32_BIOS -ErrorAction SilentlyContinue
$os = Get-CimInstance Win32_OperatingSystem -ErrorAction SilentlyContinue
$cpu = Get-CimInstance Win32_Processor -ErrorAction SilentlyContinue | Select-Object -First 1
$disks = Get-CimInstance Win32_DiskDrive -ErrorAction SilentlyContinue
$gpu = Get-CimInstance Win32_VideoController -ErrorAction SilentlyContinue | Select-Object -First 1
$nic = Get-CimInstance Win32_NetworkAdapterConfiguration -Filter 'IPEnabled = TRUE' -ErrorAction SilentlyContinue | Select-Object -First 1

# Tipo de equipo: laptop si hay batería, desktop si no.
$hasBattery = (Get-CimInstance Win32_Battery -ErrorAction SilentlyContinue) -ne $null
$assetType = if ($hasBattery) { 'laptop' } else { 'desktop' }

# Disco total (suma de todos los discos físicos en GB).
$totalDiskGb = 0
foreach ($d in $disks) {
    if ($d.Size) { $totalDiskGb += [math]::Round($d.Size / 1GB, 0) }
}

# RAM total en MB.
$ramMb = if ($cs.TotalPhysicalMemory) { [math]::Round($cs.TotalPhysicalMemory / 1MB, 0) } else { $null }

# IP local (primera adaptada con IP).
$ipAddress = if ($nic -and $nic.IPAddress) { $nic.IPAddress | Where-Object { $_ -notmatch ':' } | Select-Object -First 1 } else { $null }
$macAddress = if ($nic) { $nic.MACAddress } else { $null }

Write-Log "Recolectando software instalado (registry)..."

# Software desde registry (más confiable que WMI Win32_Product).
$softwareKeys = @(
    'HKLM:\Software\Microsoft\Windows\CurrentVersion\Uninstall\*',
    'HKLM:\Software\WOW6432Node\Microsoft\Windows\CurrentVersion\Uninstall\*',
    'HKCU:\Software\Microsoft\Windows\CurrentVersion\Uninstall\*'
)

$softwareList = @()
foreach ($key in $softwareKeys) {
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

# Quitar duplicados por (name + version).
$softwareList = $softwareList | Sort-Object name, version -Unique

# ─── Construcción del payload ───────────────────────────────────

$payload = @{
    hostname = $env:COMPUTERNAME
    serial_number = $bios.SerialNumber
    type = $assetType
    manufacturer = $cs.Manufacturer
    model = $cs.Model
    os_name = $os.Caption
    os_version = $os.Version
    os_architecture = $os.OSArchitecture
    cpu_cores = if ($cpu) { $cpu.NumberOfCores } else { $null }
    cpu_model = if ($cpu) { $cpu.Name } else { $null }
    ram_mb = $ramMb
    disk_total_gb = $totalDiskGb
    gpu_info = if ($gpu) { $gpu.Name } else { $null }
    ip_address = $ipAddress
    mac_address = $macAddress
    software = @($softwareList)
    scanned_at = (Get-Date).ToString('o')
}

$json = $payload | ConvertTo-Json -Depth 5 -Compress

Write-Log "Payload listo: $($payload.hostname) · $($payload.cpu_model) · $($payload.ram_mb)MB · $($softwareList.Count) programas instalados"

if ($DryRun) {
    Write-Log "DryRun activo. JSON que se enviaría:" 'WARN'
    Write-Output $json
    exit 0
}

# ─── Envío al endpoint ──────────────────────────────────────────

Write-Log "Enviando a $ApiUrl ..."

try {
    $response = Invoke-RestMethod -Uri $ApiUrl `
        -Method Post `
        -ContentType 'application/json' `
        -Headers @{
            'Authorization' = "Bearer $ApiToken"
            'Accept' = 'application/json'
        } `
        -Body $json `
        -TimeoutSec 30

    Write-Log "Scan registrado correctamente. Asset ID: $($response.id ?? 'N/A')"
    exit 0
}
catch {
    Write-Log "Error al enviar el scan: $($_.Exception.Message)" 'ERROR'
    if ($_.Exception.Response) {
        $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
        $body = $reader.ReadToEnd()
        Write-Log "Respuesta del servidor: $body" 'ERROR'
    }
    exit 1
}
