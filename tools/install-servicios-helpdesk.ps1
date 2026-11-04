<#
.SYNOPSIS
    Instala en el servidor los dos procesos de fondo del Helpdesk:
      1. Tarea programada "Helpdesk - Laravel Scheduler": corre
         `php artisan schedule:run` cada minuto (vigilante de IA,
         sugerencias KB, cierre automático, recordatorios, alertas SLA...).
      2. Servicio de Windows "HelpdeskConfipetrolQueue" (NSSM): procesa la
         cola de correos y trabajos en segundo plano (tools\queue-worker.bat).

.DESCRIPTION
    Se puede correr varias veces: si la tarea o el servicio ya existen,
    los actualiza. No cambia el .env: al final indica qué ajustar.

    Requisitos: PowerShell como Administrador y nssm.exe descargado
    (https://nssm.cc/download, carpeta win64) en C:\nssm\nssm.exe o en
    la ruta indicada con -NssmPath.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File tools\install-servicios-helpdesk.ps1 -PhpPath "C:\Program Files\PHP\v8.5.12\php.exe"
#>
param(
    [string]$PhpPath = '',
    [string]$NssmPath = 'C:\nssm\nssm.exe',
    [string]$ProjectPath = (Split-Path -Parent $PSScriptRoot),
    [switch]$SoloScheduler
)

$ErrorActionPreference = 'Stop'

$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    throw 'Ejecuta este script en PowerShell como Administrador.'
}

if (-not $PhpPath) {
    $cmd = Get-Command php -ErrorAction SilentlyContinue
    if ($cmd) { $PhpPath = $cmd.Source }
}
if (-not $PhpPath -or -not (Test-Path $PhpPath)) {
    throw 'No encontré php.exe. Indica la ruta con -PhpPath "C:\Program Files\PHP\v8.5.12\php.exe".'
}
if (-not (Test-Path (Join-Path $ProjectPath 'artisan'))) {
    throw "No encontré artisan en $ProjectPath. Indica la carpeta del proyecto con -ProjectPath."
}

Write-Host "Proyecto: $ProjectPath"
Write-Host "PHP:      $PhpPath"
Write-Host ''

# ── 1. Tarea programada del scheduler ─────────────────────────────────
$taskName = 'Helpdesk - Laravel Scheduler'
$action = New-ScheduledTaskAction -Execute $PhpPath -Argument 'artisan schedule:run' -WorkingDirectory $ProjectPath
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).Date -RepetitionInterval (New-TimeSpan -Minutes 1)
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable `
    -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 30)
$principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest

Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $settings -Principal $principal -Force | Out-Null
Write-Host "[OK] Tarea programada '$taskName' creada/actualizada (cada minuto, como SYSTEM)." -ForegroundColor Green

if ($SoloScheduler) {
    return
}

# ── 2. Servicio del worker de la cola (NSSM) ──────────────────────────
if (-not (Test-Path $NssmPath)) {
    Write-Warning "No encontré NSSM en $NssmPath. Descárgalo de https://nssm.cc/download, copia win64\nssm.exe a C:\nssm\ y vuelve a correr el script."
    return
}

$service = 'HelpdeskConfipetrolQueue'
$worker = Join-Path $ProjectPath 'tools\queue-worker.bat'
$logs = Join-Path $ProjectPath 'storage\logs'

if (-not (Get-Service -Name $service -ErrorAction SilentlyContinue)) {
    & $NssmPath install $service $worker | Out-Null
}

& $NssmPath set $service Application $worker | Out-Null
& $NssmPath set $service AppDirectory $ProjectPath | Out-Null
& $NssmPath set $service AppEnvironmentExtra "PHP_BIN=$PhpPath" | Out-Null
& $NssmPath set $service DisplayName 'Helpdesk Confipetrol Queue Worker' | Out-Null
& $NssmPath set $service Description 'Procesa la cola de correos y trabajos en segundo plano del Helpdesk' | Out-Null
& $NssmPath set $service Start SERVICE_AUTO_START | Out-Null
& $NssmPath set $service AppStdout (Join-Path $logs 'queue-worker.out.log') | Out-Null
& $NssmPath set $service AppStderr (Join-Path $logs 'queue-worker.err.log') | Out-Null
& $NssmPath set $service AppRotateFiles 1 | Out-Null
& $NssmPath set $service AppRotateOnline 1 | Out-Null
& $NssmPath set $service AppRotateBytes 10485760 | Out-Null
& $NssmPath set $service AppExit Default Restart | Out-Null
& $NssmPath set $service AppRestartDelay 5000 | Out-Null

Restart-Service -Name $service -ErrorAction SilentlyContinue
if ((Get-Service -Name $service).Status -ne 'Running') {
    Start-Service -Name $service
}
Write-Host "[OK] Servicio '$service' instalado y corriendo." -ForegroundColor Green

Write-Host ''
Write-Host 'Siguientes pasos:' -ForegroundColor Cyan
Write-Host '  1. En el .env de producción:  QUEUE_CONNECTION=database'
Write-Host '  2. php artisan config:cache   y   php artisan queue:restart'
Write-Host '  3. Probar: comenta un ticket de prueba; el correo al solicitante debe llegar en menos de un minuto'
Write-Host "  4. Revisar: Get-Content '$logs\queue-worker.err.log' -Tail 20   (debe estar vacío)"
Write-Host '  5. Tras unos días sin errores: MAIL_FAIL_SAFE=false en el .env (quita la red de seguridad temporal).'
