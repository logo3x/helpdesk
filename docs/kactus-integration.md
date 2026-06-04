# Integración Kactus (sistema de nómina)

Sincronización de empleados desde **Kactus** → tabla `users` del helpdesk.
Permite que los usuarios se den de alta/baja/actualicen automáticamente sin
que TI tenga que crearlos a mano.

## Estado actual

- **Código:** listo y testeado (50+ assertions, todos verdes).
- **API real:** pendiente de credenciales con **Hermes (Kactus admin)**.
- **Toggle:** `KACTUS_ENABLED=false` por defecto → todo el stack está inerte
  hasta activar.

## Componentes

| Capa | Archivo |
|------|---------|
| Config | `config/kactus.php` |
| DTO | `app/DTOs/KactusEmployee.php`, `app/DTOs/KactusSyncResult.php` |
| Service | `app/Services/KactusService.php` |
| Command | `app/Console/Commands/KactusSync.php` |
| Webhook | `app/Http/Controllers/Api/KactusWebhookController.php` |
| Job | `app/Jobs/ProcessKactusWebhookJob.php` |
| Notification | `app/Notifications/KactusSyncFailedNotification.php` |
| Migration | `database/migrations/2026_06_03_210950_add_kactus_fields_to_users.php` |
| Schedule | `routes/console.php` (hourly, weekdays 06–20) |
| UI | `app/Filament/Resources/Users/Tables/UsersTable.php` (badge + filtro + acción) |
| UI | `app/Filament/Resources/Users/Schemas/UserForm.php` (sección Kactus collapsable) |

## Tres modos de entrada

### 1. Pull programado (recomendado)
Cron horario en horario laboral si `KACTUS_ENABLED=true`:

```
php artisan kactus:sync                 # todos los modificados desde la última corrida
php artisan kactus:sync --since=2026-06-01T00:00:00Z
php artisan kactus:sync --user=K-12345  # sólo uno
php artisan kactus:sync --dry-run       # ver qué pasaría sin tocar BD
```

### 2. Webhook entrante
Kactus debe llamar:

```
POST https://helpdesk.confipetrol.com/api/kactus/webhook
Headers:
  X-Kactus-Signature: <HMAC-SHA256 del body usando KACTUS_WEBHOOK_SECRET>
  Content-Type: application/json
Body: { employee_id, document_number, first_name, last_name, email, status, ... }
```

Respuestas:
- `202` → encolado correctamente (procesado async vía queue).
- `401` → firma inválida.
- `503` → integración deshabilitada / secret no configurado.

### 3. Manual desde UI
Botón **"Sincronizar Kactus"** en cada row del módulo Usuarios (sólo visible
si el user tiene `kactus_employee_id` y `KACTUS_ENABLED=true`).

## Política de matching (upsert)

`KactusService::syncToUser()`:

1. Busca por `kactus_employee_id`.
2. Si no encuentra, busca por `identification` (cédula) — esto permite
   "adoptar" usuarios creados a mano antes de la integración.
3. Si no encuentra, **crea** el user nuevo:
   - password aleatoria (sólo bypass — login real vía Azure SSO).
   - rol = `KACTUS_DEFAULT_ROLE` (default: `usuario_final`).
   - `email_verified_at = now()` (evita el flujo de verificación).

## Política de retiro (terminated)

`KACTUS_ON_TERMINATE`:

| Valor | Acción |
|-------|--------|
| `deactivate` *(default)* | Setea `employment_status=terminated` y rota password (invalida login). Mantiene historial. |
| `delete` | Soft-delete del user. |
| `keep` | No hace nada (útil para pruebas). |

## Mapeo de departamentos

Dos estrategias en orden:

1. **Mapping explícito** en `KACTUS_DEPARTMENT_MAP` (JSON):
   ```
   KACTUS_DEPARTMENT_MAP={"Tecnologia":1,"Recursos Humanos":2,"Operaciones":3}
   ```
2. **Match por nombre exacto** contra `departments.name` (fallback).

Si ninguna resuelve, el user queda con `department_id = NULL`.

## Información que necesitamos de Hermes

- [ ] URL base de la API.
- [ ] Método de auth (Bearer? API key en header?).
- [ ] Endpoint para listar empleados (con filtro `modified_since` y paginación).
- [ ] Endpoint para fetch individual por ID.
- [ ] ¿Soporta webhooks salientes? Eventos: alta/baja/cambio de cargo/cambio de depto.
- [ ] Catálogo de departamentos de Kactus (para llenar `KACTUS_DEPARTMENT_MAP`).
- [ ] Shape exacto del payload (ej: ¿campos son `first_name`/`last_name` o `full_name`?).
  - El DTO `KactusEmployee::fromKactusPayload()` ya tolera ambos shapes; ajustar
    ahí si llega algo distinto.

## Troubleshooting

| Síntoma | Causa probable | Fix |
|---------|----------------|-----|
| `kactus:sync` responde "deshabilitado" | `KACTUS_ENABLED=false` | Setear `true` en `.env` y `php artisan config:cache` |
| Webhook responde 401 | Firma incorrecta | Verificar que el body usado para firmar es exactamente el que se envía (sin reformateo JSON) |
| Webhook responde 503 | `KACTUS_WEBHOOK_SECRET` vacío | Generar y setear secret |
| Sync queda colgado | Queue worker no corriendo | `php artisan queue:work` o configurar como servicio Windows |
| Errores en log "department null" | `KACTUS_DEPARTMENT_MAP` desalineado con catálogo Kactus | Pedirle a Hermes el listado y mapear todos |
