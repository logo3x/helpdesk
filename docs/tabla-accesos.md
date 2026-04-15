# Helpdesk Confipetrol — Tabla de Accesos para Pruebas

**Fecha:** 14 de abril de 2026  
**Servidor:** `php artisan serve` → http://localhost:8000

---

## Usuarios y paneles

| Usuario | Email | Password | Rol | Paneles con acceso |
|---|---|---|---|---|
| Administrador | `admin@confipetrol.local` | `password` | super_admin | `/admin` + `/soporte` + `/portal` |
| Supervisor | `supervisor@confipetrol.local` | `password` | supervisor_soporte | `/soporte` |
| Agente | `agente@confipetrol.local` | `password` | agente_soporte | `/soporte` |
| Usuario Final | `usuario@confipetrol.local` | `password` | usuario_final | `/portal` |

---

## Rutas principales

| Ruta | Requiere | Qué hace |
|---|---|---|
| `/admin` | super_admin, admin | Dashboard admin con stats, charts |
| `/admin/departments` | super_admin, admin | CRUD departamentos |
| `/admin/categories` | super_admin, admin | CRUD categorías |
| `/admin/assets` | super_admin, admin | Inventario de activos |
| `/admin/shield/roles` | super_admin | Gestión de roles y permisos |
| `/admin/backups` | super_admin, admin | Dashboard de backups |
| `/admin/sla-report` | super_admin, admin | Reporte SLA por departamento |
| `/soporte` | super_admin, admin, supervisor, agente, técnico, editor_kb | Dashboard soporte con stats |
| `/soporte/tickets` | ^ | Lista de tickets con filtros |
| `/soporte/tickets/create` | ^ | Crear ticket (a nombre de un usuario) |
| `/soporte/tickets/{id}` | ^ | Ver ticket + acciones (asignar, resolver...) |
| `/soporte/tickets/{id}/edit` | ^ | Editar ticket |
| `/soporte/ticket-templates` | ^ | CRUD plantillas de ticket |
| `/soporte/canned-responses` | ^ | CRUD respuestas predefinidas |
| `/soporte/kb-articles` | ^ | CRUD artículos base de conocimiento |
| `/portal/tickets` | cualquier auth | Mis tickets (solo los propios) |
| `/portal/tickets/create` | cualquier auth | Crear ticket como usuario |
| `/portal/tickets/{id}` | dueño del ticket | Ver detalle + comentar |
| `/portal/chatbot` | cualquier auth | Asistente virtual (chatbot) |
| `/auth/azure` | ninguno | Redirige a Microsoft SSO |
| `/auth/azure/callback` | ninguno | Callback OAuth de Azure |
| `POST /api/inventory/web-scan` | auth:sanctum (cookie) | Web scan desde browser |
| `POST /api/inventory/agent-scan` | auth:sanctum (token) | Agent scan PowerShell |

---

## Qué probar por rol

### Como admin (`admin@confipetrol.local` → `/admin`)

- [ ] Dashboard: ver 5 stats + 2 gráficos
- [ ] Departamentos: crear, editar, desactivar
- [ ] Categorías: ver agrupadas por departamento, crear nueva
- [ ] Inventario: ver lista de activos (vacía hasta que alguien visite el portal)
- [ ] Shield → Roles: ver los 7 roles con 62+ permisos
- [ ] Backups: ver dashboard, ejecutar backup manual
- [ ] Reporte SLA: ver matriz departamento × prioridad

### Como agente (`agente@confipetrol.local` → `/soporte`)

- [ ] Dashboard: ver 4 stats operativos + 3 SLA
- [ ] Tickets: ver lista con filtro "Solo abiertos" activo por default
- [ ] Badge en nav: número de tickets nuevos/reabiertos
- [ ] Crear ticket: formulario con prioridad calculada en vivo
- [ ] Ver ticket TK-2026-00002: botones Resolver, Cerrar disponibles
- [ ] Comentarios: agregar público + interno (candado)
- [ ] Plantillas: crear una plantilla de ticket
- [ ] Respuestas predefinidas: crear una canned response
- [ ] KB: crear un artículo borrador, publicarlo

### Como usuario (`usuario@confipetrol.local` → `/portal`)

- [ ] Mis tickets: ver los 3 tickets demo
- [ ] Buscar por número o asunto
- [ ] Filtrar por estado
- [ ] Crear ticket: llenar formulario, adjuntar archivo, enviar
- [ ] Ver ticket: ver detalle + adjuntos + comentarios públicos
- [ ] Agregar comentario en ticket abierto
- [ ] Chatbot: escribir "contraseña" → debe iniciar flujo de reset
- [ ] Chatbot: escribir "vpn" → debe iniciar flujo VPN
- [ ] Chatbot: escribir "crear ticket" → debe ofrecer escalar
- [ ] Chatbot: escribir "escalar: prueba" → debe crear ticket

---

## Tickets demo sembrados

| Número | Asunto | Estado | Prioridad | Asignado |
|---|---|---|---|---|
| TK-2026-00001 | Pantalla no enciende | Nuevo | Media | — |
| TK-2026-00002 | Correo no recibe externos | En progreso | Crítica | Agente Soporte |
| TK-2026-00003 | Reporte nómina no genera | Resuelto | Baja | Supervisor |

---

## Roles y permisos

| Rol | Panel | Descripción |
|---|---|---|
| super_admin | /admin + /soporte | Control total del sistema |
| admin | /admin + /soporte | Administración funcional |
| supervisor_soporte | /soporte | Supervisa grupos de soporte |
| agente_soporte | /soporte | Atiende tickets |
| tecnico_campo | /soporte | Técnicos en sitio |
| editor_kb | /soporte | Gestiona base de conocimiento |
| usuario_final | /portal | Crea y consulta sus propios tickets |

---

## Datos sembrados

| Dato | Cantidad |
|---|---|
| Roles | 7 |
| Departamentos | 5 (TI, RRHH, Compras, Mantenimiento, Operaciones) |
| Categorías | 19 (distribuidas por departamento) |
| Configuraciones SLA | 25 (5 prioridades × 5 departamentos) |
| Flujos de chatbot | 3 (contraseña, VPN, impresoras) |
| Usuarios demo | 4 (admin, supervisor, agente, usuario) |
| Tickets demo | 3 (nuevo, en progreso, resuelto) |

---

## Comandos útiles

```bash
# Levantar servidor
php artisan serve

# Levantar con queue + vite (dev completo)
composer run dev

# Recrear BD desde cero con todos los datos
php artisan migrate:fresh --seed --force

# Correr tests
php artisan test --compact

# Ejecutar backup manual
php artisan backup:run --only-db

# Ver rutas registradas
php artisan route:list

# Correr scheduler manualmente
php artisan schedule:work

# Resetear password de un usuario
php artisan tinker --execute "App\Models\User::where('email','admin@confipetrol.local')->update(['password'=>bcrypt('nuevopass')]);"
```

---

*Documento generado el 14 de abril de 2026.*
