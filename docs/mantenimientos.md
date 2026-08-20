# Módulo Mantenimientos Programados

Guía operativa del módulo (Sprint 2). Los conceptos técnicos están en los
comentarios del código; este archivo cubre **cómo se usa en el día a día**.

## Quién puede hacer qué

| Rol | Ver | Crear | Editar | Borrar |
|-----|-----|-------|--------|--------|
| super_admin, admin | todos | ✅ | ✅ | ✅ |
| supervisor_soporte | los del **depto** (via asset.department_id) | ✅ | ✅ | ✅ |
| agente_soporte, tecnico_campo | **solo los que se les asignaron** | ❌ | ✅ (los suyos) | ❌ |
| usuario_final, editor_kb | — | — | — | — |

Además se requiere que el depto del user tenga `can_access_inventory = true`.

## URLs

- Panel Soporte: `/soporte/scheduled-maintenances`
- Panel Admin: `/admin/scheduled-maintenances`
- Informes: `/soporte/maintenances-report`

## Ciclo de vida

```
    Crear                Editar (agente)              Editar (cierre)
      │                        │                             │
      ▼                        ▼                             ▼
  ┌────────┐   observations  ┌────────┐   status=cumplido  ┌────────┐
  │Pendient│ ───────────────>│Pendient│ ─────────────────> │Cumplido│
  └────────┘                 └────────┘                     └────┬───┘
                                  │                              │
                                  │ status=no_cumplido           │
                                  │ + not_completed_reason       │
                                  ▼                              ▼
                            ┌─────────────┐             (observer dispara:
                            │ NoCumplido  │              1. AssetHistory
                            └─────────────┘              2. Asset.last_mtto
                                                         3. Siguiente ciclo)
```

Al cerrar un ciclo (cumplido o no_cumplido) el observer:

1. **Registra en `asset_histories`** con `action='maintenance'` + observaciones.
   Aparece en la hoja de vida del activo.
2. Si es **cumplido**: actualiza `assets.last_maintenance_at` y
   `assets.maintenance_interval_days` según la frecuencia.
3. **Auto-genera la siguiente ocurrencia** con
   `scheduled_at = fecha_original + días_frecuencia`.
   Mantiene el ciclo aunque el actual haya sido no_cumplido.

## Programación individual

Cuando querés programar mtto a un activo específico:

1. **Desde /soporte/scheduled-maintenances → Crear** →
   elegir "📄 Un solo activo".
2. **Desde /soporte/assets → menú ⋮ de una fila → "Programar
   mantenimiento"** — abre el form con el activo precargado.

El Select de activo tiene **búsqueda rica**: buscás por TAG, hostname,
serial, código SAP, nombre del custodio, cédula, gerencia, campo o zona.
Cada opción muestra 2 líneas: `TAG · TIPO · Hostname · Serial` /
`Custodio (CC) · Proyecto · Gerencia · Campo · Zona`.

## Programación masiva

Para jornadas de mtto en zona/gerencia/proyecto:

1. Crear → elegir **"📦 Varios activos (programación masiva)"**.
2. Aplicá los filtros que necesites (todos opcionales):
   Tipo, Gerencia, Campo, Zona, Proyecto, Departamento.
3. **Encima del select "Activos"** aparece un contador:
   *"Hay N activos que matchean los filtros actuales"*.
4. Hacé click en el select "Activos" → aparecen los que matchean.
   Podés escribir para filtrar más (TAG, cédula, etc.).
5. Seleccioná varios (multi-select). El contador cambia a
   *"Vas a crear X mantenimientos"*.
6. Completá **Programación**: agente, fecha, frecuencia (los tres
   son iguales para todos los del lote).
7. Click **Crear**.

**Comportamiento al guardar:**
- Se crea 1 registro `ScheduledMaintenance` por activo (N registros).
- Al agente se le envía **UNA sola notificación consolidada** en la
  campanita: *"Se te asignaron X mantenimientos"*, con link a la lista.
  No llegan X campanitas duplicadas.

## Alertas de vencimiento

- **Job:** `NotifyDueMaintenancesJob` corre **diario a las 7:15am**.
- Notifica pendientes con `scheduled_at ≤ hoy + MAINTENANCE_ALERT_DAYS_BEFORE`
  (default 30 días).
- Marca `notified_due_at = now()` para no re-notificar. Idempotente.
- Env var configurable: `MAINTENANCE_ALERT_DAYS_BEFORE=30`.

**Backfill tras deploy inicial:** para no esperar al cron del día
siguiente, corré manualmente:

```bash
php artisan maintenances:backfill-notifications
```

## Informes

`/soporte/maintenances-report` — acceso solo super_admin/admin/supervisor.

Muestra:
- **KPIs** (4 tarjetas): total, cumplidos + %, no cumplidos, vencidos.
- **Cumplimiento mensual** (Chart.js barras apiladas).
- **Top 10 razones de no cumplimiento** (agrupadas case-insensitive).
- **Ranking por agente** con % de cumplimiento coloreado.
- **Drill-down**: tabla de todos los "no cumplidos" con motivo.

Ventana configurable: 30 / 90 / 180 / 365 días (Livewire live).

**Exportar a PDF** — botón en el header. Genera archivo letter portrait
con toda la data del reporte (mismo bundle que la vista).

## Integración con Inventario

- Los mantenimientos aparecen en la **hoja de vida del activo**
  (`/soporte/assets/{id}/lifecycle` → historial) como eventos
  `action='maintenance'` con las observaciones.
- La opción "Mantenimiento" del select del modal "Registrar evento
  en historial" **fue removida**. Los mtto solo se crean desde el
  módulo dedicado, garantizando trazabilidad completa (programación,
  agente, ciclo, motivo).
- El botón "Registrar mantenimiento" de la tabla de assets también
  fue removido. En su lugar hay "Programar mantenimiento" que linkea
  al Create del módulo con el activo precargado.

## Comandos artisan

```bash
# One-shot post-deploy: dispara alertas de vencimiento sin esperar cron
php artisan maintenances:backfill-notifications
```

## Env vars

```env
# Días de anticipación para alerta de vencimiento (default 30)
MAINTENANCE_ALERT_DAYS_BEFORE=30
```

## Tests

- `tests/Feature/ScheduledMaintenanceObserverTest.php` — ciclo,
  historial, siguiente ocurrencia, no duplicado, isOverdue.
- `tests/Feature/NotifyDueMaintenancesJobTest.php` — ventana,
  no re-notificación, ignora cerrados.
- `tests/Feature/ScheduledMaintenanceCreationTest.php` — creación
  individual, bulk simulado, mapeo de frecuencia a días.

```bash
php artisan test --filter="Maintenance|Scheduled" --compact
```
