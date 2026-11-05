# Procesos de fondo en Windows Server (producción)

El Helpdesk necesita dos procesos corriendo siempre en el servidor. Los dos
se instalan como **tareas programadas de Windows**, sin software adicional.

| Tarea | Qué hace | Frecuencia |
|---|---|---|
| `Helpdesk - Laravel Scheduler` | `php artisan schedule:run`: respaldos, vigilante de IA, sugerencias KB, cierre automático, recordatorios al cliente, alertas SLA | Cada minuto |
| `Helpdesk - Queue Worker` | [tools/queue-worker.bat](../tools/queue-worker.bat): procesa la cola `database` (correos y trabajos en segundo plano) | Siempre vivo; se revisa cada minuto |

> **¿Por qué no NSSM?** Microsoft Defender lo marca como "software
> potencialmente no deseado" y bloquea la descarga, porque también lo usa
> malware. Una tarea programada con *una sola instancia a la vez* que se
> revisa cada minuto cumple la misma función: si el worker termina, se
> relanza en menos de un minuto.

## Instalación

PowerShell **como Administrador**, en la carpeta del proyecto:

```powershell
cd C:\inetpub\wwwroot\helpdesk
powershell -ExecutionPolicy Bypass -File tools\install-servicios-helpdesk.ps1 -PhpPath "C:\Program Files\PHP\v8.5.12\php.exe"
```

Deben salir dos `[OK]`. El script se puede correr de nuevo cuando quieras
(actualiza las tareas). Si detecta otra tarea que también corre
`schedule:run`, avisa: hay que borrarla, porque dos schedulers ejecutan
cada trabajo dos veces.

Luego, en el `.env`:

```
QUEUE_CONNECTION=database
```

```powershell
php artisan config:cache
php artisan queue:restart
```

## Verificación

```powershell
Get-ScheduledTask -TaskName 'Helpdesk*' | Select-Object TaskName, State
Get-ScheduledTask -TaskName 'Helpdesk - Laravel Scheduler' | Get-ScheduledTaskInfo | Select-Object LastRunTime, LastTaskResult
Get-Content storage\logs\queue-worker.err.log -Tail 20
php artisan tinker --execute 'echo DB::table("jobs")->count();'
```

- El worker debe estar en **Running** y el scheduler en **Ready** con `LastTaskResult` = `0`.
- `queue-worker.err.log` vacío (o sin errores recientes).
- La tabla `jobs` vuelve a 0 a los pocos segundos de comentar un ticket.

## Operaciones cotidianas

**Después de un deploy:** `tools\deploy.bat` llama a `php artisan queue:restart`.
El worker termina tras el trabajo actual y la tarea lo relanza con el
código nuevo en menos de un minuto.

**Cola y fallos:**

```powershell
php artisan queue:failed       # trabajos fallidos
php artisan queue:retry all    # reintentarlos
php artisan queue:flush        # borrar los fallidos
```

**Detener / arrancar el worker:**

```powershell
Disable-ScheduledTask -TaskName 'Helpdesk - Queue Worker'; Stop-ScheduledTask -TaskName 'Helpdesk - Queue Worker'
Enable-ScheduledTask -TaskName 'Helpdesk - Queue Worker'; Start-ScheduledTask -TaskName 'Helpdesk - Queue Worker'
```

(Solo `Stop-ScheduledTask` no basta: la tarea lo relanzaría al minuto.)

**Quitar todo:**

```powershell
Unregister-ScheduledTask -TaskName 'Helpdesk - Queue Worker' -Confirm:$false
Unregister-ScheduledTask -TaskName 'Helpdesk - Laravel Scheduler' -Confirm:$false
```

## Solución de problemas

| Síntoma | Causa probable | Solución |
|---|---|---|
| La tabla `jobs` no se vacía | Worker detenido o `QUEUE_CONNECTION` distinto de `database` | Revisa el estado de la tarea y el `.env`; corre `config:cache` |
| `"php" no se reconoce` en `queue-worker.err.log` | Ruta de PHP incorrecta | Vuelve a correr el script con `-PhpPath` correcto |
| Errores de conexión a MySQL en el log | Credenciales o MySQL caído | Revisa `DB_*` en el `.env` y el servicio de MySQL |
| `LastTaskResult` = `2147946720` (0x800710E0) en el worker | Es normal: la tarea intentó lanzar otro worker y Windows lo rechazó porque ya hay uno corriendo | Nada; confirma con `(Get-ScheduledTask -TaskName 'Helpdesk - Queue Worker').State` → `Running` |
| Los correos tardan hasta un minuto | El worker se estaba reiniciando (cada hora o tras un deploy) | Es normal |
| Respaldos o recordatorios duplicados | Dos tareas corriendo `schedule:run` | Deja solo `Helpdesk - Laravel Scheduler` |

## Desarrollo local

[tools/queue-worker-dev.bat](../tools/queue-worker-dev.bat) o, a mano,
`tools\queue-worker.bat` en una CMD (Ctrl+C para detenerlo).
