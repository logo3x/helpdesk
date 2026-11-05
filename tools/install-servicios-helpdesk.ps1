<#
.SYNOPSIS
    Instala en el servidor los dos procesos de fondo del Helpdesk, ambos
    como tareas programadas de Windows (sin NSSM ni otro software):
      1. "Helpdesk - Laravel Scheduler": corre `php artisan schedule:run`
         cada minuto (respaldos, vigilante de IA, sugerencias KB, cierre
         automático, recordatorios al cliente, alertas SLA...).
      2. "Helpdesk - Queue Worker": mantiene vivo tools\queue-worker.bat,
         que procesa la cola de correos y trabajos en segundo plano. Se
         revisa cada minuto y solo corre una instancia a la vez; si el
         worker termina (cada hora o tras queue:restart) se relanza solo.

.DESCRIPTION
    Se puede correr varias veces: si las tareas ya existen, las actualiza.
    No cambia el .env: al final indica qué ajustar.

    Requisito: PowerShell como Administrador.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File tools\install-servicios-helpdesk.ps1 -PhpPath "C:\Program Files\PHP\v8.5.12\php.exe"
#>
param(
    [string]$PhpPath = '',
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
$PhpPath = (Resolve-Path $PhpPath).Path
$ProjectPath = (Resolve-Path $ProjectPath).Path
if (-not (Test-Path (Join-Path $ProjectPath 'artisan'))) {
    throw "No encontré artisan en $ProjectPath. Indica la carpeta del proyecto con -ProjectPath."
}

Write-Host "Proyecto: $ProjectPath"
Write-Host "PHP:      $PhpPath"
Write-Host ''

# Aviso si quedó alguna tarea vieja que también corra schedule:run: dos
# schedulers ejecutarían cada tarea dos veces (respaldos, correos...).
$duplicates = Get-ScheduledTask | Where-Object {
    $_.TaskName -ne 'Helpdesk - Laravel Scheduler' -and
    ($_.Actions | Where-Object { "$($_.Execute) $($_.Arguments)" -match 'schedule:run' })
}
foreach ($duplicate in $duplicates) {
    Write-Warning "La tarea '$($duplicate.TaskName)' también corre schedule:run. Bórrala con: Unregister-ScheduledTask -TaskName '$($duplicate.TaskName)' -Confirm:`$false"
}

$principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
$everyMinute = New-ScheduledTaskTrigger -Once -At (Get-Date).Date -RepetitionInterval (New-TimeSpan -Minutes 1)

# ── 1. Scheduler ──────────────────────────────────────────────────────
$schedulerTask = 'Helpdesk - Laravel Scheduler'
$action = New-ScheduledTaskAction -Execute $PhpPath -Argument 'artisan schedule:run' -WorkingDirectory $ProjectPath
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable `
    -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 30)

Register-ScheduledTask -TaskName $schedulerTask -Action $action -Trigger $everyMinute -Settings $settings -Principal $principal -Force | Out-Null
Write-Host "[OK] Tarea '$schedulerTask' creada/actualizada (cada minuto, como SYSTEM)." -ForegroundColor Green

if ($SoloScheduler) {
    return
}

# ── 2. Worker de la cola ──────────────────────────────────────────────
# El worker termina solo cada hora (--max-time=3600); la tarea se
# revisa cada minuto y, como ignora instancias nuevas mientras corre,
# siempre hay exactamente un worker vivo.
$workerTask = 'Helpdesk - Queue Worker'
$worker = Join-Path $ProjectPath 'tools\queue-worker.bat'
$errLog = Join-Path $ProjectPath 'storage\logs\queue-worker.err.log'

# cmd /c "" ... "": las comillas externas las quita cmd y conserva las internas.
$workerArgs = "/c `"`"$worker`" `"$PhpPath`" > nul 2>> `"$errLog`"`""
$action = New-ScheduledTaskAction -Execute 'cmd.exe' -Argument $workerArgs -WorkingDirectory $ProjectPath
$atStartup = New-ScheduledTaskTrigger -AtStartup
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable `
    -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Hours 2) `
    -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1)

Register-ScheduledTask -TaskName $workerTask -Action $action -Trigger @($atStartup, $everyMinute) -Settings $settings -Principal $principal -Force | Out-Null
Start-ScheduledTask -TaskName $workerTask
Write-Host "[OK] Tarea '$workerTask' creada/actualizada y arrancada (como SYSTEM)." -ForegroundColor Green

Write-Host ''
Write-Host 'Siguientes pasos:' -ForegroundColor Cyan
Write-Host '  1. En el .env de producción:  QUEUE_CONNECTION=database'
Write-Host '  2. php artisan config:cache   y   php artisan queue:restart'
Write-Host "  3. Verificar: Get-ScheduledTask -TaskName 'Helpdesk*' | Select TaskName, State   (el worker en Running)"
Write-Host '  4. Probar: comenta un ticket de prueba; el correo al solicitante debe llegar en menos de un minuto'
Write-Host "  5. Revisar: Get-Content '$errLog' -Tail 20   (debe estar vacío)"
Write-Host '  6. Tras unos días sin errores: MAIL_FAIL_SAFE=false en el .env (quita la red de seguridad temporal).'
