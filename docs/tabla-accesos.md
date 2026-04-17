# Helpdesk Confipetrol — Tabla de Accesos para Pruebas

**Fecha:** 17 de abril de 2026  
**Versión:** 1.5 (módulo usuarios + scope por departamento)  
**Servidor:** `php artisan serve` → http://localhost:8000

---

## Usuarios demo (6 usuarios, todos con password `password`)

| Usuario | Email | Rol | Departamento | Paneles con acceso |
|---|---|---|---|---|
| Administrador | `admin@confipetrol.local` | super_admin | — | `/admin` + `/soporte` + `/portal` |
| Supervisor TI | `supervisor@confipetrol.local` | supervisor_soporte | TI | `/soporte` |
| Agente TI | `agente@confipetrol.local` | agente_soporte | TI | `/soporte` |
| Supervisor RRHH | `supervisor.rrhh@confipetrol.local` | supervisor_soporte | RRHH | `/soporte` |
| Agente RRHH | `agente.rrhh@confipetrol.local` | agente_soporte | RRHH | `/soporte` |
| Usuario Final | `usuario@confipetrol.local` | usuario_final | Operaciones | `/portal` |

---

## Rutas principales

| Ruta | Requiere | Qué hace |
|---|---|---|
| `/admin` | super_admin, admin | Dashboard admin con stats, charts |
| `/admin/users` | super_admin, admin | **CRUD usuarios + asignar rol + departamento** |
| `/admin/departments` | super_admin, admin | CRUD departamentos |
| `/admin/categories` | super_admin, admin | CRUD categorías (cada una pertenece a un depto) |
| `/admin/assets` | super_admin, admin | Inventario de activos |
| `/admin/shield/roles` | super_admin | Gestión de roles y permisos |
| `/admin/backups` | super_admin, admin | Dashboard de backups |
| `/admin/sla-report` | super_admin, admin | Reporte SLA por departamento |
| `/soporte` | supervisor, agente, admin | Dashboard soporte con stats SLA |
| `/soporte/users` | supervisor, admin | **Crear agentes para tu departamento** |
| `/soporte/tickets` | supervisor, agente, admin | Lista de tickets (filtrada por depto) |
| `/soporte/tickets/create` | supervisor, agente, admin | Crear ticket (a nombre de cualquier usuario) |
| `/soporte/tickets/{id}` | supervisor, agente, admin | Ver ticket + acciones (**incluye "Trasladar a otro depto"** para supervisor+) |
| `/soporte/tickets/{id}/edit` | supervisor, agente, admin | Editar ticket |
| `/soporte/ticket-templates` | supervisor, agente, admin | **Plantillas pre-llenadas de tickets** (tabla con columnas visibles) |
| `/soporte/canned-responses` | supervisor, agente, admin | **Respuestas predefinidas para comentarios** (tabla con columnas visibles) |
| `/soporte/kb-articles` | supervisor, agente, admin | Base de conocimiento (agentes crean borradores, supervisores publican) |
| `/portal/tickets` | cualquier auth | Mis tickets (solo los propios) |
| `/portal/tickets/create` | cualquier auth | Crear ticket como usuario final |
| `/portal/tickets/{id}` | dueño del ticket | Ver detalle + comentar (solo públicos) |
| `/portal/chatbot` | cualquier auth | Asistente virtual (chatbot) |
| `/auth/azure` | ninguno | Redirige a Microsoft SSO |
| `/auth/azure/callback` | ninguno | Callback OAuth de Azure |
| `POST /api/inventory/web-scan` | auth:sanctum (cookie) | Web scan desde browser |
| `POST /api/inventory/agent-scan` | auth:sanctum (token) | Agent scan PowerShell |

---

## Matriz de permisos por rol (v1.5)

### Creación de tickets

| Rol | `/portal/tickets/create` | `/soporte/tickets/create` |
|:---:|:---:|:---:|
| super_admin | ✅ | ✅ |
| supervisor_soporte | — | ✅ |
| agente_soporte | — | ✅ |
| usuario_final | ✅ (para sí mismo) | — |

### Scope de visibilidad de tickets

| Rol | Qué tickets ve |
|---|---|
| super_admin / admin | Todos de todos los departamentos |
| supervisor_soporte (TI) | Todos los tickets del depto TI |
| agente_soporte (TI) | Solo sus asignados + sin asignar del depto TI |
| supervisor_soporte (RRHH) | Todos los tickets del depto RRHH (no ve TI) |
| agente_soporte (RRHH) | Solo sus asignados + sin asignar de RRHH |
| usuario_final | Solo los tickets que él creó (/portal) |

### Acciones sobre tickets

| Acción | super_admin | supervisor | agente | usuario_final |
|---|:---:|:---:|:---:|:---:|
| Ver | ✅ | ✅ (su depto) | ✅ (sus asignados) | ✅ (los propios) |
| Crear (panel Soporte) | ✅ | ✅ | ✅ | — |
| Crear (portal) | ✅ | — | — | ✅ |
| Editar | ✅ | ✅ | ✅ | — |
| Asignar / reasignar | ✅ | ✅ | ✅ (a sí mismo) | — |
| Marcar primera respuesta | ✅ | ✅ | ✅ | — |
| Resolver | ✅ | ✅ | ✅ | — |
| Cerrar | ✅ | ✅ | ✅ | — |
| Reabrir | ✅ | ✅ | ✅ | — |
| **Trasladar depto** | ✅ | ✅ | ❌ | — |
| **Eliminar** (soft) | ✅ | ✅ | ❌ | — |
| Restaurar / force-delete | ✅ | ✅ | ❌ | — |
| Comentar público | ✅ | ✅ | ✅ | ✅ (si abierto) |
| Comentar interno | ✅ | ✅ | ✅ | — |

### Acciones sobre KB / Plantillas / Canned Responses

| Acción | super_admin | supervisor | agente |
|---|:---:|:---:|:---:|
| Ver | ✅ | ✅ (su depto) | ✅ (su depto) |
| Crear | ✅ | ✅ | ✅ |
| Editar | ✅ | ✅ | ✅ |
| Eliminar | ✅ | ✅ | ❌ |
| **KB: publicar/archivar** | ✅ | ✅ | ❌ (solo crea borrador) |

### Gestión de usuarios

| Acción | super_admin | supervisor_soporte |
|---|:---:|:---:|
| Crear cualquier usuario + elegir rol + depto | ✅ en `/admin/users` | ❌ |
| Crear agentes para su depto (rol forzado) | ✅ | ✅ en `/soporte/users` |
| Ver usuarios de todos los deptos | ✅ | ❌ (solo su depto) |
| Eliminar usuarios | ✅ | ✅ (solo de su depto) |

---

## Qué probar por rol

### 1. Como super_admin (`admin@confipetrol.local` → `/admin`)

- [ ] Login → Dashboard con 5 stats + 2 gráficos
- [ ] **Usuarios: crear un nuevo agente para depto Compras**
- [ ] Departamentos: crear "Logística", editar, desactivar
- [ ] Categorías: ver agrupadas por departamento, crear nueva
- [ ] Inventario: ver lista de activos
- [ ] Shield → Roles: ver los 7 roles con permisos
- [ ] Backups: ver dashboard, ejecutar backup manual
- [ ] Reporte SLA: ver matriz departamento × prioridad
- [ ] Cambiar al panel /soporte (super_admin accede a todos los paneles)

### 2. Como supervisor (`supervisor@confipetrol.local` → `/soporte`)

- [ ] Login → Dashboard con widgets SLA
- [ ] **Usuarios: crear un agente nuevo** (el rol y depto se fuerzan)
- [ ] **Ver todos los tickets de TI** (no ve RRHH)
- [ ] Abrir un ticket mal clasificado → **acción "Trasladar a otro depto."** con motivo
- [ ] Verificar que el usuario solicitante recibe notificación del traslado
- [ ] Eliminar ticket (bulk delete disponible)
- [ ] KB: **publicar un borrador creado por un agente** (agente no puede)
- [ ] Intentar entrar a `/admin` → **403 Forbidden**

### 3. Como agente (`agente@confipetrol.local` → `/soporte`)

- [ ] Login → Dashboard
- [ ] **Ver solo sus asignados + sin asignar del depto TI**
- [ ] NO ver tickets de RRHH
- [ ] Crear ticket a nombre de un usuario (con plantilla pre-llenada)
- [ ] Asignar → Marcar primera respuesta → Resolver → Cerrar → Reabrir
- [ ] Comentarios públicos + internos (candado)
- [ ] **Plantillas: crear una** (categoría solo muestra TI)
- [ ] **Canned response: crear una compartida**
- [ ] KB: crear borrador (NO puede publicar, solo borrador)
- [ ] NO aparece botón "Eliminar" en ningún ticket
- [ ] NO aparece acción "Trasladar depto"

### 4. Como agente RRHH (`agente.rrhh@confipetrol.local` → `/soporte`)

- [ ] Login → Dashboard
- [ ] **Ver solo tickets del depto RRHH** (aislamiento entre deptos)
- [ ] NO ver tickets de TI
- [ ] Plantillas y canned responses: solo muestra categorías RRHH

### 5. Como usuario final (`usuario@confipetrol.local` → `/portal`)

- [ ] Login → `/portal/tickets`
- [ ] Ver solo tickets propios (3 inicialmente)
- [ ] Buscar por número o asunto
- [ ] Filtrar por estado
- [ ] Crear ticket: form con prioridad calculada en vivo
- [ ] Agregar archivo adjunto
- [ ] Ver ticket: detalle + comentarios públicos (**NO ve internos**)
- [ ] Comentar en ticket abierto
- [ ] Ticket "Resuelto" → NO permite comentar
- [ ] Chatbot: flujos password/VPN/impresoras
- [ ] Chatbot: escalar a ticket
- [ ] Recibir notificación si un supervisor traslada su ticket de depto

---

## Datos demo sembrados (tras `php artisan migrate:fresh --seed --force`)

| Recurso | Cantidad |
|---|---|
| Roles | 7 (super_admin, admin, supervisor_soporte, agente_soporte, tecnico_campo, editor_kb, usuario_final) |
| Departamentos | 5 (TI, RRHH, Compras, Mantenimiento, Operaciones) |
| Categorías | 19 (distribuidas por depto) |
| Config SLA | 25 (5 prioridades × 5 deptos) |
| Flujos chatbot | 3 (password, VPN, impresoras) |
| Usuarios | 6 (admin + supervisor TI + agente TI + supervisor RRHH + agente RRHH + usuario final) |
| Tickets demo | 3 (TK-2026-00001 Nuevo, TK-2026-00002 En progreso, TK-2026-00003 Resuelto) |
| Comentarios | 2 (1 público + 1 interno en TK-2026-00002) |

---

## Comandos rápidos

```bash
# Reset completo
php artisan migrate:fresh --seed --force

# Solo volver a sembrar permisos (útil si agregas resources)
php artisan db:seed --class=ShieldPermissionSeeder --force

# Build assets
npm run build

# Levantar servidor
php artisan serve

# Ver rutas disponibles
php artisan route:list --except-vendor

# Limpiar caches si algo raro
php artisan optimize:clear
```

---

*Documento actualizado 2026-04-17 — v1.5 con módulo usuarios, scope por depto y traslado de tickets.*
