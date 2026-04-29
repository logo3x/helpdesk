# Queue Worker en Windows Server (producción)

Esta guía explica cómo dejar corriendo el worker de Laravel `queue:work`
como un **servicio de Windows** que arranca automáticamente con el sistema,
se reinicia si cae, sobrevive a reboots y es supervisable desde el panel
de servicios estándar de Windows.

## Contexto

- **Driver**: `QUEUE_CONNECTION=database` (usa la tabla `jobs` de MySQL).
- **Servidor**: Windows Server con MySQL.
- **PHP**: el mismo que sirve la app (típicamente bajo IIS o XAMPP/WampServer).
- **Por qué un servicio y no una tarea programada**: las tareas
  programadas no son ideales para procesos largos; los servicios sí.

## Arquitectura

```
┌─────────────────────────────────────────────────────────────┐
│  Windows Server                                             │
│                                                             │
│   ┌───────────────────┐      ┌───────────────────────────┐  │
│   │  IIS / Apache     │      │  Servicio: HelpdeskQueue  │  │
│   │  → sirve la app   │      │  (NSSM + queue-worker.bat)│  │
│   │  → recibe HTTP    │      │  → consume jobs de MySQL  │  │
│   │  → encola jobs    │      │  → reinicia c/hora        │  │
│   └─────────┬─────────┘      └─────────────┬─────────────┘  │
│             │                              │                │
│             └────────────┬─────────────────┘                │
│                          ▼                                  │
│                  ┌───────────────┐                          │
│                  │  MySQL        │                          │
│                  │  ├─ jobs      │                          │
│                  │  ├─ failed_jobs                          │
│                  │  └─ notifications                        │
│                  └───────────────┘                          │
└─────────────────────────────────────────────────────────────┘
```

## Paso a paso

### 1. Descargar NSSM (Non-Sucking Service Manager)

Es la herramienta estándar para correr cualquier ejecutable como servicio
en Windows. Open source, bajo MIT, sin dependencias.

- Descarga: https://nssm.cc/download
- Versión recomendada: la última estable (2.24+).
- Extrae `nssm.exe` (de `win64\`) a `C:\nssm\`.

### 2. Verificar el script `queue-worker.bat`

Está en [tools/queue-worker.bat](../tools/queue-worker.bat). Si tu PHP
**no está en el PATH**, edita la línea:

```bat
set PHP_BIN=php
```

Y ponle la ruta absoluta. Ejemplo Wampserver:

```bat
set PHP_BIN=C:\wamp64\bin\php\php8.5.0\php.exe
```

Pruébalo a mano primero (debe quedarse esperando jobs):

```cmd
.\tools\queue-worker.bat
```

Si imprime sin errores y se queda en idle ("waiting"), está OK. Ctrl+C
para detenerlo.

### 3. Registrar el servicio con NSSM

Abre `cmd.exe` **como administrador** y corre:

```cmd
C:\nssm\nssm.exe install HelpdeskConfipetrolQueue
```

Se abre un GUI. Configura:

| Pestaña | Campo | Valor |
|---|---|---|
| **Application** | Path | `C:\wamp64\www\helpdesk\helpdesk\tools\queue-worker.bat` (ajusta a tu ruta real) |
| **Application** | Startup directory | `C:\wamp64\www\helpdesk\helpdesk` |
| **Details** | Display name | `Helpdesk Confipetrol Queue Worker` |
| **Details** | Description | `Procesa la cola de notificaciones y jobs en background` |
| **Details** | Startup type | `Automatic` |
| **I/O** | Output (stdout) | `C:\wamp64\www\helpdesk\helpdesk\storage\logs\queue-worker.out.log` |
| **I/O** | Error (stderr) | `C:\wamp64\www\helpdesk\helpdesk\storage\logs\queue-worker.err.log` |
| **File rotation** | Replace existing Output/Error files | ☑ |
| **File rotation** | Rotate files | ☑ |
| **File rotation** | Rotate while service is running | ☑ |
| **File rotation** | Rotate Files larger than | `10485760` (10 MB) |
| **Exit actions** | Default action on exit | `Restart application` |
| **Exit actions** | Delay restart by | `5000` ms |

Click **Install service**.

> Lo mismo en CLI sin GUI:
>
> ```cmd
> nssm install HelpdeskConfipetrolQueue "C:\wamp64\www\helpdesk\helpdesk\tools\queue-worker.bat"
> nssm set HelpdeskConfipetrolQueue AppDirectory "C:\wamp64\www\helpdesk\helpdesk"
> nssm set HelpdeskConfipetrolQueue Description "Procesa la cola de notificaciones y jobs"
> nssm set HelpdeskConfipetrolQueue Start SERVICE_AUTO_START
> nssm set HelpdeskConfipetrolQueue AppStdout "C:\wamp64\www\helpdesk\helpdesk\storage\logs\queue-worker.out.log"
> nssm set HelpdeskConfipetrolQueue AppStderr "C:\wamp64\www\helpdesk\helpdesk\storage\logs\queue-worker.err.log"
> nssm set HelpdeskConfipetrolQueue AppRotateFiles 1
> nssm set HelpdeskConfipetrolQueue AppRotateOnline 1
> nssm set HelpdeskConfipetrolQueue AppRotateBytes 10485760
> nssm set HelpdeskConfipetrolQueue AppExit Default Restart
> nssm set HelpdeskConfipetrolQueue AppRestartDelay 5000
> ```

### 4. Iniciar el servicio

```cmd
nssm start HelpdeskConfipetrolQueue
```

O abre `services.msc`, busca "Helpdesk Confipetrol Queue Worker" y dale
**Iniciar**. Verifica que el estado quede en "En ejecución".

### 5. Verificación

```cmd
REM ¿está corriendo?
sc query HelpdeskConfipetrolQueue

REM ¿procesa los jobs?
php artisan tinker --execute "echo 'Jobs pendientes: ' . DB::table('jobs')->count();"

REM ver los logs en tiempo real (PowerShell)
Get-Content storage\logs\queue-worker.out.log -Tail 20 -Wait
```

Crea un ticket de prueba desde el portal y verifica que la notificación
aparezca en la campanita en segundos. Si tarda, mira el log de stderr.

## Operaciones cotidianas

### Después de un deploy

Ejecuta [tools/after-deploy.bat](../tools/after-deploy.bat) — limpia
caches, corre migraciones y llama a `php artisan queue:restart`. NSSM
reiniciará el worker automáticamente con el código nuevo.

### Ver qué jobs están en cola / fallaron

```cmd
php artisan queue:work --once --queue=default
php artisan queue:failed
php artisan queue:retry all
php artisan queue:flush
```

### Detener / reiniciar el servicio

```cmd
nssm stop HelpdeskConfipetrolQueue
nssm start HelpdeskConfipetrolQueue
nssm restart HelpdeskConfipetrolQueue
```

### Quitar el servicio

```cmd
nssm stop HelpdeskConfipetrolQueue
nssm remove HelpdeskConfipetrolQueue confirm
```

## Solución de problemas

| Síntoma | Causa probable | Solución |
|---|---|---|
| Servicio en "Started" pero `jobs` table no se vacía | PHP no encuentra `vendor/` o `.env` | Verifica `Application directory` apunta a la raíz del proyecto |
| `php no se reconoce` en `queue-worker.err.log` | PHP no está en PATH | Edita `PHP_BIN` en `queue-worker.bat` con ruta absoluta |
| Worker se reinicia cada 5s en bucle | Error de conexión a MySQL | Revisa `.env` `DB_*` y que el servicio MySQL esté arriba |
| Notificaciones llegan al log pero no a la BD | `MAIL_MAILER=log` y `database` channel separado | Es esperado si la notif tiene `via=['mail']` solamente. Las del Helpdesk usan `['mail', 'database']` |
| Memoria del worker crece sin parar | Memory leak en alguna notification | El `--max-time=3600` y `--max-jobs=1000` reciclan el worker. NSSM lo reinicia limpio. |

## Recordatorios

- **Cada vez que actualices el código** (deploy), corre `after-deploy.bat`
  para que el worker recoja los cambios. Sin esto, sigue ejecutando la
  versión vieja en memoria hasta que reinicie.
- **El `.env` debe tener** `QUEUE_CONNECTION=database` en producción.
- **Failed jobs**: `failed_jobs` table los acumula. Revísala al menos
  una vez por semana (`php artisan queue:failed`).
- **Backup**: la tabla `jobs` puede crecer si la cola se atasca. El
  `after-deploy.bat` no la toca, pero ten un script aparte si necesitas
  drenar manualmente.
