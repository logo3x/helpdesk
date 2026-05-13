<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Sirve los scripts de instalación y desinstalación del agente
 * PowerShell con la URL de la API ya rellenada, para que IT pueda
 * desplegar / retirar el agente con UNA sola línea por PC.
 *
 * Flujo:
 *   1. Admin genera un token en /admin → Inventario → Generar token.
 *   2. En cada PC, IT pega:
 *
 *      iex (irm "https://helpdesk.confipetrol.com/agent/install?token=1|abcd...")
 *
 *      El script descarga el agente, cifra el token con DPAPI
 *      (LocalMachine scope) para que solo SYSTEM lo pueda leer,
 *      crea dos triggers en Task Scheduler (lunes 9 AM + AtStartup
 *      con delay de 5 min) y dispara el primer scan.
 *
 *   3. Para desinstalar:
 *
 *      iex (irm "https://helpdesk.confipetrol.com/agent/uninstall")
 *
 *      Borra la tarea programada y la carpeta C:\ProgramData\HelpdeskConfipetrol.
 */
class InventoryAgentController extends Controller
{
    /**
     * GET /agent/install?token=...
     *
     * Devuelve un script PowerShell que:
     *   - Descarga el agente desde /downloads/inventory-agent.ps1
     *   - Cifra el token con DPAPI LocalMachine y lo guarda en token.enc
     *   - Crea una tarea programada (lunes 9 AM + AtStartup) como SYSTEM
     *   - Dispara un primer scan de inmediato
     */
    public function install(Request $request): Response
    {
        $token = (string) $request->query('token', '');
        $apiUrl = url('/api/inventory/agent-scan');
        // Servimos el .ps1 desde Laravel (no como static file) para que
        // IIS no lo bloquee con 404.3 — los .ps1 no están en el MIME
        // map por defecto y agregarlos requiere config server-side
        // que se pierde entre redeployments.
        $scriptUrl = url('/agent/script');

        if ($token !== '' && ! preg_match('/^\d+\|[A-Za-z0-9]+$/', $token)) {
            return response('# Token inválido. Genera uno desde /admin → Inventario → Generar token del agente.', 400)
                ->header('Content-Type', 'text/plain; charset=utf-8');
        }

        $tokenLine = $token !== ''
            ? "\$Token = '{$token}'"
            : "\$Token = Read-Host 'Pega el token Sanctum del agente'";

        $script = <<<PS1
# ────────────────────────────────────────────────────────────────────
# Helpdesk Confipetrol — Instalador del agente de inventario (v2)
# Generado dinámicamente por /agent/install
#
# Uso:
#   iex (irm "{$apiUrl}/install?token=TU_TOKEN")
#
# Lo que hace:
#   1. Descarga inventory-agent.ps1 a C:\ProgramData\HelpdeskConfipetrol\
#   2. Cifra el token con DPAPI (LocalMachine) — solo SYSTEM puede leerlo
#   3. Crea tarea programada con dos triggers:
#        - Cada lunes 9 AM (regular)
#        - AtStartup con delay de 5 min (retry si lunes la PC estaba off)
#      Ambos corren como SYSTEM (sin contraseña expuesta)
#   4. Dispara un primer scan de inmediato
# ────────────────────────────────────────────────────────────────────

\$ErrorActionPreference = 'Stop'
\$InstallDir = 'C:\\ProgramData\\HelpdeskConfipetrol'
\$ScriptPath = Join-Path \$InstallDir 'inventory-agent.ps1'
\$TokenEncPath = Join-Path \$InstallDir 'token.enc'
\$ApiUrl = '{$apiUrl}'
\$ScriptUrl = '{$scriptUrl}'

{$tokenLine}

if (-not (Test-Path \$InstallDir)) {
    New-Item -ItemType Directory -Path \$InstallDir -Force | Out-Null
}

# ACL: solo SYSTEM y Administrators tienen acceso a la carpeta.
# Esto previene que un usuario local con permisos vea token.enc o el log.
try {
    \$acl = Get-Acl \$InstallDir
    \$acl.SetAccessRuleProtection(\$true, \$false)
    \$acl.Access | ForEach-Object { \$acl.RemoveAccessRule(\$_) | Out-Null }
    \$systemRule = New-Object System.Security.AccessControl.FileSystemAccessRule(
        'NT AUTHORITY\\SYSTEM', 'FullControl',
        'ContainerInherit,ObjectInherit', 'None', 'Allow'
    )
    \$adminsRule = New-Object System.Security.AccessControl.FileSystemAccessRule(
        'BUILTIN\\Administrators', 'FullControl',
        'ContainerInherit,ObjectInherit', 'None', 'Allow'
    )
    \$acl.AddAccessRule(\$systemRule)
    \$acl.AddAccessRule(\$adminsRule)
    Set-Acl -Path \$InstallDir -AclObject \$acl
} catch {
    Write-Host "ADVERTENCIA: no se pudo endurecer ACL de \$InstallDir : \$(\$_.Exception.Message)" -ForegroundColor Yellow
}

Write-Host "[1/4] Descargando agente desde \$ScriptUrl ..." -ForegroundColor Cyan
Invoke-WebRequest -Uri \$ScriptUrl -OutFile \$ScriptPath -UseBasicParsing

# 2. Cifrar el token con DPAPI LocalMachine para que solo SYSTEM (y procesos
#    con la misma máquina) lo puedan descifrar. token.txt en plain text era
#    legible por cualquier user con read en la carpeta — token.enc no.
Write-Host "[2/4] Cifrando token con DPAPI (LocalMachine)..." -ForegroundColor Cyan
Add-Type -AssemblyName System.Security
\$plainBytes = [System.Text.Encoding]::UTF8.GetBytes(\$Token)
\$encBytes = [System.Security.Cryptography.ProtectedData]::Protect(
    \$plainBytes, \$null, [System.Security.Cryptography.DataProtectionScope]::LocalMachine
)
[System.IO.File]::WriteAllBytes(\$TokenEncPath, \$encBytes)

# Borrar el legacy token.txt si quedó de una instalación v1.
\$LegacyTokenPath = Join-Path \$InstallDir 'token.txt'
if (Test-Path \$LegacyTokenPath) { Remove-Item \$LegacyTokenPath -Force }

# 3. Tarea programada con dos triggers: weekly + atStartup (con delay).
\$TaskName = 'HelpdeskConfipetrolInventory'
\$Action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument (
    "-NoProfile -ExecutionPolicy Bypass -File `"\$ScriptPath`" -ApiUrl `"\$ApiUrl`""
)

\$WeeklyTrigger = New-ScheduledTaskTrigger -Weekly -DaysOfWeek Monday -At 9am

# Para el AtStartup necesitamos la API CIM directa porque
# New-ScheduledTaskTrigger no soporta Delay nativamente.
\$StartupTrigger = New-ScheduledTaskTrigger -AtStartup
\$StartupTrigger.Delay = 'PT5M'

\$Principal = New-ScheduledTaskPrincipal -UserId 'NT AUTHORITY\\SYSTEM' -LogonType ServiceAccount -RunLevel Highest
\$Settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -ExecutionTimeLimit (New-TimeSpan -Minutes 10)

Register-ScheduledTask -TaskName \$TaskName `
    -Action \$Action `
    -Trigger @(\$WeeklyTrigger, \$StartupTrigger) `
    -Principal \$Principal `
    -Settings \$Settings `
    -Force | Out-Null
Write-Host "[3/4] Tarea '\$TaskName' creada (lunes 9 AM + AtStartup+5min)." -ForegroundColor Cyan

# 4. Primer scan inmediato. El agente lee el token cifrado de token.enc
#    en lugar de recibirlo por parámetro (evita exponer el token en
#    el árbol de procesos vía línea de comandos).
Write-Host "[4/4] Ejecutando primer scan ..." -ForegroundColor Cyan
& \$ScriptPath -ApiUrl \$ApiUrl

Write-Host ""
Write-Host "✅ Agente v2 instalado correctamente." -ForegroundColor Green
Write-Host "   Script:    \$ScriptPath"
Write-Host "   Token:     \$TokenEncPath (cifrado DPAPI)"
Write-Host "   Tarea:     \$TaskName"
Write-Host "   Próximo:   lunes 9:00 AM (y al reiniciar +5 min)"
Write-Host ""
Write-Host "Para desinstalar:  iex (irm '{$apiUrl}/../uninstall' -replace 'api/', '')"
PS1;

        return response($script, 200)
            ->header('Content-Type', 'text/plain; charset=utf-8');
    }

    /**
     * GET /agent/script
     *
     * Devuelve el contenido de `public/downloads/inventory-agent.ps1`
     * con Content-Type `text/plain` para que IIS no lo bloquee como
     * extensión desconocida y los browsers no lo intenten "abrir" como
     * descarga arbitraria.
     *
     * El instalador (`/agent/install`) referencia ESTA URL y no la
     * static, así no dependemos del MIME map de IIS.
     */
    public function script(): Response
    {
        $path = public_path('downloads/inventory-agent.ps1');

        if (! is_file($path)) {
            return response('# Agente no disponible. Verifica el deploy.', 404)
                ->header('Content-Type', 'text/plain; charset=utf-8');
        }

        $content = (string) file_get_contents($path);

        return response($content, 200)
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('Cache-Control', 'no-store, must-revalidate');
    }

    /**
     * GET /agent/uninstall
     *
     * Devuelve un script PowerShell que retira por completo el agente:
     *   - Borra la tarea programada
     *   - Borra la carpeta C:\ProgramData\HelpdeskConfipetrol
     *
     * No revoca el token Sanctum — eso se hace desde /admin (Users →
     * Tokens) porque el mismo token puede estar en uso en varias PCs
     * y la decisión de revocar es del admin, no del PC individual.
     */
    public function uninstall(Request $request): Response
    {
        $script = <<<'PS1'
# ────────────────────────────────────────────────────────────────────
# Helpdesk Confipetrol — Desinstalador del agente de inventario
# Generado dinámicamente por /agent/uninstall
#
# Uso:
#   iex (irm "https://helpdesk.confipetrol.com/agent/uninstall")
#
# Lo que hace:
#   1. Detiene y elimina la tarea programada
#   2. Borra C:\ProgramData\HelpdeskConfipetrol (script + token + log)
#
# NO revoca el token Sanctum — el admin debe hacerlo desde /admin si
# quiere invalidarlo para todas las PCs.
# ────────────────────────────────────────────────────────────────────

$ErrorActionPreference = 'Continue'
$InstallDir = 'C:\ProgramData\HelpdeskConfipetrol'
$TaskName = 'HelpdeskConfipetrolInventory'

Write-Host "[1/2] Eliminando tarea programada '$TaskName'..." -ForegroundColor Cyan
$task = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
if ($task) {
    Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false
    Write-Host "      Tarea eliminada." -ForegroundColor Green
} else {
    Write-Host "      No estaba registrada." -ForegroundColor Yellow
}

Write-Host "[2/2] Eliminando $InstallDir..." -ForegroundColor Cyan
if (Test-Path $InstallDir) {
    Remove-Item -Path $InstallDir -Recurse -Force -ErrorAction SilentlyContinue
    if (-not (Test-Path $InstallDir)) {
        Write-Host "      Carpeta eliminada." -ForegroundColor Green
    } else {
        Write-Host "      Algunos archivos no se pudieron borrar (¿proceso en uso?). Reintenta tras reiniciar." -ForegroundColor Yellow
    }
} else {
    Write-Host "      No existía." -ForegroundColor Yellow
}

Write-Host ""
Write-Host "✅ Agente desinstalado." -ForegroundColor Green
Write-Host "   Si quieres invalidar el token Sanctum en TODAS las PCs:"
Write-Host "   /admin → Usuarios → ver tokens → revocar."
PS1;

        return response($script, 200)
            ->header('Content-Type', 'text/plain; charset=utf-8');
    }
}
