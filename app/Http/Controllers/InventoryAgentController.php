<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Sirve el "instalador" del agente PowerShell con la URL de la API
 * y (opcionalmente) el token ya rellenados, para que IT pueda
 * desplegar el agente con UNA sola línea por PC.
 *
 * Flujo simplificado:
 *   1. Admin genera un token en /admin → Inventario → Generar token.
 *   2. Comparte con IT solo el token.
 *   3. En cada PC, IT pega:
 *
 *      iex (irm "https://helpdesk.confipetrol.com/agent/install?token=1|abcd...")
 *
 *      Eso descarga el script, crea una tarea programada semanal
 *      y dispara un primer scan de inmediato.
 *
 * Sin esto, IT tendría que descargar .ps1, editar el comando con el
 * token, copiar a cada PC, y crear la tarea programada a mano.
 */
class InventoryAgentController extends Controller
{
    /**
     * GET /agent/install?token=...
     *
     * Devuelve un script PowerShell que:
     *   - Descarga el agente desde /downloads/inventory-agent.ps1
     *   - Lo guarda en C:\ProgramData\HelpdeskConfipetrol\
     *   - Crea una tarea programada que lo ejecuta semanalmente
     *   - Dispara un primer scan de inmediato
     */
    public function install(Request $request): Response
    {
        $token = (string) $request->query('token', '');
        $apiUrl = url('/api/inventory/agent-scan');
        $scriptUrl = url('/downloads/inventory-agent.ps1');

        // Sanitizar el token: solo permitir caracteres válidos de Sanctum.
        // Formato esperado: <id>|<random> donde random es alfanumérico.
        if ($token !== '' && ! preg_match('/^\d+\|[A-Za-z0-9]+$/', $token)) {
            return response('# Token inválido. Genera uno desde /admin → Inventario → Generar token del agente.', 400)
                ->header('Content-Type', 'text/plain; charset=utf-8');
        }

        $tokenLine = $token !== ''
            ? "\$Token = '{$token}'"
            : "\$Token = Read-Host 'Pega el token Sanctum del agente'";

        $script = <<<PS1
# ────────────────────────────────────────────────────────────────────
# Helpdesk Confipetrol — Instalador del agente de inventario
# Generado dinámicamente por el endpoint /agent/install
#
# Uso:
#   iex (irm "{$apiUrl}/install?token=TU_TOKEN")
#
# Lo que hace:
#   1. Descarga inventory-agent.ps1 a C:\ProgramData\HelpdeskConfipetrol\
#   2. Crea una tarea programada que lo ejecuta cada lunes 9 AM como SYSTEM
#   3. Dispara un primer scan de inmediato
# ────────────────────────────────────────────────────────────────────

\$ErrorActionPreference = 'Stop'
\$InstallDir = 'C:\\ProgramData\\HelpdeskConfipetrol'
\$ScriptPath = Join-Path \$InstallDir 'inventory-agent.ps1'
\$ApiUrl = '{$apiUrl}'
\$ScriptUrl = '{$scriptUrl}'

{$tokenLine}

# 1. Crear directorio si no existe.
if (-not (Test-Path \$InstallDir)) {
    New-Item -ItemType Directory -Path \$InstallDir -Force | Out-Null
}

Write-Host "[1/4] Descargando agente desde \$ScriptUrl ..." -ForegroundColor Cyan
Invoke-WebRequest -Uri \$ScriptUrl -OutFile \$ScriptPath -UseBasicParsing

# 2. Guardar token de forma protegida (DPAPI por usuario actual).
\$TokenFile = Join-Path \$InstallDir 'token.txt'
\$Token | Out-File -FilePath \$TokenFile -Encoding utf8 -NoNewline
Write-Host "[2/4] Token guardado en \$TokenFile" -ForegroundColor Cyan

# 3. Registrar tarea programada (semanal, lunes 9 AM, como SYSTEM).
\$TaskName = 'HelpdeskConfipetrolInventory'
\$Action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument (
    "-NoProfile -ExecutionPolicy Bypass -File \"\$ScriptPath\" -ApiUrl \"\$ApiUrl\" -ApiToken (Get-Content \"\$TokenFile\" -Raw)"
)
\$Trigger = New-ScheduledTaskTrigger -Weekly -DaysOfWeek Monday -At 9am
\$Principal = New-ScheduledTaskPrincipal -UserId 'NT AUTHORITY\\SYSTEM' -LogonType ServiceAccount -RunLevel Highest
\$Settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable

Register-ScheduledTask -TaskName \$TaskName -Action \$Action -Trigger \$Trigger -Principal \$Principal -Settings \$Settings -Force | Out-Null
Write-Host "[3/4] Tarea programada '\$TaskName' creada (cada lunes 9 AM)." -ForegroundColor Cyan

# 4. Disparar un primer scan de inmediato.
Write-Host "[4/4] Ejecutando primer scan ..." -ForegroundColor Cyan
& \$ScriptPath -ApiUrl \$ApiUrl -ApiToken \$Token

Write-Host ""
Write-Host "✅ Agente instalado correctamente." -ForegroundColor Green
Write-Host "   Script:    \$ScriptPath"
Write-Host "   Tarea:     \$TaskName (Task Scheduler)"
Write-Host "   Próximo:   lunes 9:00 AM"
PS1;

        return response($script, 200)
            ->header('Content-Type', 'text/plain; charset=utf-8');
    }
}
