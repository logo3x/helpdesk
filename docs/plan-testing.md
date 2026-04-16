# Plan de Testing — Helpdesk Confipetrol

**Fecha:** 16 de abril de 2026
**Versión:** 1.2 (post-pruebas TestSprite MCP + fixes P0)

## Changelog

- **v1.2 (2026-04-16)** — TestSprite Round 2 (29/29 ejecutados, 20 pass = 69%). Se descubrieron 3 bugs P0 (formularios Filament Soporte vacíos). **Corregidos en este ciclo:**
  - `KbArticleForm` → ahora tiene título/slug/body/categoría/estado/visibilidad/published_at
  - `CannedResponseForm` → título/body/categoría/orden/compartida/activa
  - `TicketTemplateForm` → nombre/asunto/descripción/categoría/impacto/urgencia/activa
  - Además: `FORTIFY_LOGIN_LIMIT` configurable, Parte 8 (escenarios TestSprite por rol) agregada.
- **v1.1 (2026-04-15)** — post-pruebas Playwright

---

## Prerequisitos

```bash
# 1. Recrear BD limpia con todos los datos demo
php artisan migrate:fresh --seed --force

# 2. Compilar assets (OBLIGATORIO para que el portal funcione)
npm run build

# 3. Levantar servidor
php artisan serve
```

Abrir: http://127.0.0.1:8000

---

## Credenciales

| Email | Password | Rol | Panel(es) |
|---|---|---|---|
| admin@confipetrol.local | password | super_admin | /admin + /soporte + /portal |
| supervisor@confipetrol.local | password | supervisor_soporte | /soporte |
| agente@confipetrol.local | password | agente_soporte | /soporte |
| usuario@confipetrol.local | password | usuario_final | /portal |

---

## Datos demo sembrados

| Dato | Cantidad | Detalles |
|---|---|---|
| Roles | 7 | super_admin, admin, supervisor_soporte, agente_soporte, tecnico_campo, editor_kb, usuario_final |
| Departamentos | 5 | TI, RRHH, Compras, Mantenimiento, Operaciones |
| Categorías | 19 | Distribuidas por departamento |
| Config SLA | 25 | 5 prioridades × 5 departamentos |
| Flujos chatbot | 3 | Reset password, VPN, Impresoras |
| Usuarios | 4 | admin, supervisor, agente, usuario |
| Tickets | 3 | TK-2026-00001 (Nuevo), TK-2026-00002 (En progreso), TK-2026-00003 (Resuelto) |
| Comentarios | 2 | 1 público + 1 interno en TK-2026-00002 |

---

## PARTE 1 — Panel Admin (/admin)

**Login:** admin@confipetrol.local / password
**URL:** http://127.0.0.1:8000/admin/login

### 1.1 Dashboard
- [ ] Login exitoso → redirige a /admin
- [ ] Ver stat "Tickets abiertos": **2** (Sin SLA vencidos)
- [ ] Ver stat "Total tickets": **3** (Histórico completo)
- [ ] Ver stat "Usuarios": **4** (0 activos en inventario)
- [ ] Ver stat "KB publicados": **0**
- [ ] Ver stat "Satisfacción (CSAT)": **—**
- [ ] Ver gráfico "Tickets creados (últimos 30 días)" con líneas creados/resueltos
- [ ] Ver gráfico "Tickets por estado" (doughnut con colores)
- [ ] Menú lateral muestra: Escritorio, Filament Shield > Roles, Configuraciones > Respaldos, Inventario > Inventario, Configuración > Departamentos + Categorías, Reportes > Reporte SLA

### 1.2 Departamentos (/admin/departments)
- [ ] Ver 5 departamentos: TI, RRHH, Compras, Mantenimiento, Operaciones
- [ ] Columnas: Nombre, Slug, Padre, Usuarios, Activo
- [ ] TI muestra "2" usuarios (agente + supervisor)
- [ ] Click "Crear Departamento"
- [ ] Llenar Nombre: "Logística" → verificar slug se autogenera "logistica"
- [ ] Guardar → aparece en la lista
- [ ] Editar "Logística" → cambiar descripción → Guardar
- [ ] Verificar filtro Activos/Inactivos funciona

### 1.3 Categorías (/admin/categories)
- [ ] Ver 19 categorías agrupadas por departamento (Department: Compras, Department: Mantenimiento, etc.)
- [ ] Columnas: Departamento (badge), Nombre, Tickets, Activa, Orden
- [ ] Click "Crear Categoría"
- [ ] Seleccionar departamento, llenar nombre → slug autogenerado
- [ ] Guardar → verificar que aparece en el grupo correcto
- [ ] Verificar filtro por departamento
- [ ] Verificar filtro activas/inactivas

### 1.4 Inventario (/admin/assets)
- [ ] Tabla vacía inicialmente
- [ ] Después de visitar /portal (como usuario logueado), verificar que aparece un registro de web scan
- [ ] Verificar botón "Exportar Excel" en acciones bulk

### 1.5 Shield → Roles (/admin/shield/roles)
- [ ] Ver 7 roles listados
- [ ] Click en "super_admin" → verificar acceso total
- [ ] Click en "agente_soporte" → verificar permisos de Ticket, KbArticle, CannedResponse, TicketTemplate

### 1.6 Respaldos (/admin/backups)
- [ ] Ver dashboard de Spatie Backup
- [ ] Ejecutar backup manual (solo BD)
- [ ] Verificar archivo creado en storage/app/backups/

### 1.7 Reporte SLA (/admin/sla-report)
- [ ] Ver tabla "Cumplimiento SLA por departamento (últimos 30 días)"
- [ ] 5 departamentos × 5 prioridades (Planificada, Baja, Media, Alta, Crítica)
- [ ] RRHH/Baja muestra "100%" con "1 tickets"
- [ ] Los demás muestran "—"
- [ ] Ver sección "Últimas escalaciones" (vacía o con datos si pasó tiempo)

---

## PARTE 2 — Panel Soporte (/soporte)

**Login:** agente@confipetrol.local / password
**URL:** http://127.0.0.1:8000/soporte/login

### 2.1 Dashboard
- [ ] Login exitoso → redirige a /soporte
- [ ] Ver "SLA Compliance (30d)": **100%**
- [ ] Ver "Tiempo promedio 1ra respuesta": **0 min**
- [ ] Ver "CSAT (30d)": **—**
- [ ] Ver "Tickets abiertos": **2**
- [ ] Ver "Sin asignar / reabiertos": **1**
- [ ] Ver "Prioridad alta/crítica": **1**
- [ ] Ver "Asignados a mí": **1**
- [ ] Menú lateral: Escritorio, Tickets (con badge "1"), Base De Conocimiento, Configuración > Plantillas + Respuestas Predefinidas

### 2.2 Lista de Tickets (/soporte/tickets)
- [ ] Filtro "Solo abiertos" activo por defecto (badge "Filtros activos")
- [ ] Ver 2 tickets: TK-2026-00001 (Nuevo, Media) y TK-2026-00002 (En progreso, Crítica)
- [ ] Columnas: Número (copiable), Asunto, Estado (badge color), Prioridad (badge color), Solicitante, Asignado a, Departamento, Categoría, Creado
- [ ] Badge "1" en menú lateral (tickets nuevos/reabiertos)
- [ ] Click icono filtros → verificar: Estado, Prioridad, Impacto, Urgencia, Departamento, Categoría, Asignado a, Solo abiertos, Asignados a mí, Archivados
- [ ] Activar "Asignados a mí" → solo TK-2026-00002

### 2.3 Ver Ticket (/soporte/tickets/1)
- [ ] Título: "Ver TK-2026-00001"
- [ ] Sección Identificación: Número, Asunto "Pantalla no enciende", Descripción completa
- [ ] Sección Clasificación: Impacto Medio, Urgencia Media, Prioridad Media, Estado Nuevo
- [ ] Sección Partes: Solicitante "Usuario Final", Asignado "Sin asignar", Departamento TI, Categoría Hardware
- [ ] Sección Adjuntos (vacía)
- [ ] Sección Tiempos (colapsada)
- [ ] **Botones header**: Editar, Asignar, Marcar primera respuesta

### 2.4 Asignar Ticket
1. En la vista de TK-2026-00001:
- [ ] Click "Asignar"
- [ ] Se abre modal con dropdown de agentes
- [ ] Seleccionar "Agente Soporte"
- [ ] Confirmar → notificación "Ticket asignado" verde
- [ ] Estado cambia a "Asignado"
- [ ] Botón "Asignar" sigue visible, ahora "Marcar primera respuesta" también

### 2.5 Marcar Primera Respuesta
- [ ] Click "Marcar primera respuesta" → confirmar
- [ ] Estado cambia a "En progreso"
- [ ] Botón desaparece (ya se marcó)
- [ ] Aparece botón "Resolver"

### 2.6 Resolver Ticket
- [ ] Click "Resolver" → confirmar
- [ ] Estado cambia a "Resuelto"
- [ ] Aparece botón "Cerrar" y "Reabrir"

### 2.7 Cerrar Ticket
- [ ] Click "Cerrar" → confirmar
- [ ] Estado cambia a "Cerrado"
- [ ] Solo queda botón "Reabrir"
- [ ] Ticket desaparece de la lista (filtro "Solo abiertos")

### 2.8 Reabrir Ticket
- [ ] Quitar filtro "Solo abiertos" → ver ticket cerrado
- [ ] Click "Ver" → click "Reabrir" → confirmar
- [ ] Estado cambia a "Reabierto"
- [ ] Vuelve a aparecer en la lista con filtro activo

### 2.9 Agregar Comentarios
1. En vista de TK-2026-00002 (tab "Comentarios"):
- [ ] Ver 2 comentarios existentes
- [ ] Comentario público del agente: "Estoy revisando los logs..."
- [ ] Comentario interno del supervisor: icono candado + "Nota interna: verificar si coincide..."
- [ ] Click "Crear" (nuevo comentario)
- [ ] Escribir "Ya identifiqué el problema, es un tema de DNS"
- [ ] Toggle "Comentario interno" en OFF → Guardar
- [ ] Verificar que aparece sin candado
- [ ] Crear otro con toggle interno ON → verificar candado

### 2.10 Crear Ticket desde Soporte (/soporte/tickets/create)
- [ ] Formulario con 4 secciones: Identificación, Clasificación, Partes y asignación, Adjuntos
- [ ] Número: placeholder "TK-YYYY-NNNNN (se asigna al guardar)" (disabled)
- [ ] Llenar Asunto: "Equipo nuevo para contabilidad"
- [ ] Llenar Descripción: "Se requiere un equipo para el nuevo empleado del área contable"
- [ ] Impacto: "Bajo", Urgencia: "Baja" → Prioridad muestra "Planificada" automáticamente
- [ ] Cambiar Impacto a "Alto", Urgencia a "Alta" → Prioridad cambia a "Crítica"
- [ ] Seleccionar Solicitante: "Usuario Final"
- [ ] Seleccionar Departamento y Categoría
- [ ] Click "Crear" → redirige a vista del ticket con número TK-2026-00004 o mayor
- [ ] Verificar que el ticket aparece en la lista

### 2.11 Base de Conocimiento (/soporte/kb-articles)

> **⚠️ REGRESIÓN A VERIFICAR** — Este formulario estaba vacío hasta 2026-04-16. Ya está implementado; verificar que todos los campos aparezcan.

- [ ] Tabla vacía inicialmente
- [ ] Click "Crear" → debe abrir formulario con sección "Contenido" + "Clasificación"
- [ ] **Sección Contenido:**
  - Título (obligatorio) → al llenarlo, Slug se auto-genera
  - Slug (único, editable)
  - Cuerpo (MarkdownEditor con toolbar)
- [ ] **Sección Clasificación:**
  - Categoría KB (select, con botón "Crear" inline para nueva categoría)
  - Estado: "Borrador" / "Publicado" / "Archivado"
  - Visibilidad: "Pública" / "Interna"
  - Fecha de publicación (visible solo cuando Estado = Publicado)
- [ ] Guardar → aparece en lista
- [ ] Editar → cambiar algo → Guardar

### 2.12 Plantillas (/soporte/ticket-templates)

> **⚠️ REGRESIÓN A VERIFICAR** — Formulario recién implementado (2026-04-16).

- [ ] Click "Crear" → formulario con sección "Plantilla" + "Clasificación pre-asignada"
- [ ] **Sección Plantilla:**
  - Nombre interno (obligatorio, ej: "Solicitud de equipo")
  - Asunto del ticket (obligatorio, ej: "Solicito equipo para nuevo empleado")
  - Descripción pre-rellenada (MarkdownEditor)
- [ ] **Sección Clasificación pre-asignada:**
  - Categoría (select)
  - Impacto por defecto (Bajo/Medio/Alto)
  - Urgencia por defecto (Baja/Media/Alta)
  - Orden (numérico)
  - Activa (toggle, default ON)
- [ ] Guardar → aparece en lista

### 2.13 Respuestas Predefinidas (/soporte/canned-responses)

> **⚠️ REGRESIÓN A VERIFICAR** — Formulario recién implementado (2026-04-16). Antes crasheaba con SQL error al guardar.

- [ ] Click "Crear" → formulario con sección "Respuesta" + "Clasificación"
- [ ] **Sección Respuesta:**
  - Título (obligatorio, ej: "Ticket recibido")
  - Contenido (MarkdownEditor, ej: "Hemos recibido tu solicitud. Un agente te contactará pronto.")
- [ ] **Sección Clasificación:**
  - Categoría (select)
  - Orden (numérico)
  - Compartida con todo el equipo (toggle, default ON)
  - Activa (toggle, default ON)
- [ ] Guardar → aparece en lista (antes: error 500 SQL `Field 'title' doesn't have a default value`)

### 2.14 Exportar Excel
- [ ] En /soporte/tickets, seleccionar checkbox de 1+ tickets
- [ ] Click "Acciones" → "Exportar Excel"
- [ ] Verificar descarga de archivo .xlsx

---

## PARTE 3 — Portal de Usuario (/portal)

**Login:** usuario@confipetrol.local / password
**URL:** http://127.0.0.1:8000/login (login Fortify estándar)

### 3.1 Login y Navegación
- [ ] Login con usuario@confipetrol.local / password
- [ ] Redirige a /dashboard
- [ ] Navegar a /portal → redirige a /portal/tickets
- [ ] Header nav: "Helpdesk Confipetrol", "Crear ticket", "Mis tickets" (activo), "Asistente", perfil (UF)

### 3.2 Mis Tickets (/portal/tickets)
- [ ] Ver 3 tickets con cards:
  - TK-2026-00002: En progreso, Crítica, "Correo no recibe mensajes externos"
  - TK-2026-00003: Resuelto, Baja, "Reporte de horas de nómina no genera"
  - TK-2026-00001: Nuevo, Media, "Pantalla no enciende"
- [ ] Cada card muestra: número (badge), estado (badge color), prioridad (badge color), asunto, categoría, asignado, tiempo relativo
- [ ] Buscador: escribir "correo" → solo muestra TK-2026-00002
- [ ] Filtro estado: seleccionar "Resuelto" → solo TK-2026-00003
- [ ] Limpiar filtros → vuelven los 3

### 3.3 Crear Ticket (/portal/tickets/create)
- [ ] Formulario: Asunto, Descripción, Categoría (dropdown), Impacto, Urgencia, Prioridad calculada, Adjuntos
- [ ] Validación: asunto mín 5 chars, descripción mín 10 chars
- [ ] Intentar enviar vacío → errores de validación
- [ ] Llenar: Asunto "No puedo acceder al sistema de nómina", Descripción "Al intentar entrar al sistema de nómina me muestra error 403 forbidden..."
- [ ] Categoría: "Nómina"
- [ ] Impacto "Alto" + Urgencia "Media" → Prioridad calculada: "Alta"
- [ ] (Opcional) Adjuntar un archivo
- [ ] Click "Enviar ticket"
- [ ] Redirige a /portal/tickets/{id} con toast "Ticket creado correctamente"
- [ ] Número asignado: TK-2026-00004 o 00005

### 3.4 Ver Ticket (/portal/tickets/{id})
1. Click en TK-2026-00002 desde la lista:
- [ ] Header: botón "Volver", badges TK-2026-00002 + En progreso + Crítica
- [ ] Título: "Correo no recibe mensajes externos"
- [ ] Metadata: creado hace X, categoría, asignado
- [ ] Descripción en caja gris
- [ ] Sección Comentarios: **solo 1 comentario visible** (público del agente)
- [ ] **El comentario interno del supervisor NO aparece** (IMPORTANTE: seguridad)
- [ ] Formulario "Agregar comentario" visible (ticket abierto)

2. Ver TK-2026-00003 (Resuelto):
- [ ] **NO hay formulario de comentario** — muestra "Este ticket está Resuelto — no se pueden agregar comentarios"

### 3.5 Agregar Comentario
1. En TK-2026-00002:
- [ ] Escribir "El problema sigue, no he recibido correos externos todo el día"
- [ ] Click "Enviar comentario"
- [ ] Comentario aparece con burbuja azul (es del solicitante)
- [ ] Timestamp "hace unos segundos"

### 3.6 Seguridad — Acceso a tickets ajenos
- [ ] Intentar ir a /portal/tickets/999 → Error 404 o 403
- [ ] (Si hay tickets de otros usuarios con IDs conocidos) Verificar 403 Forbidden

### 3.7 Chatbot — Flujo Reset Password (/portal/chatbot)
- [ ] Mensaje de bienvenida del asistente
- [ ] Escribir "password" → responde con flujo: "¿Qué contraseña necesitas resetear?"
- [ ] Escribir "1" → instrucciones de reset Windows
- [ ] Escribir "sí" → flujo completado

### 3.8 Chatbot — Flujo VPN
- [ ] Recargar página (nueva sesión)
- [ ] Escribir "vpn" → responde con instrucciones FortiClient

### 3.9 Chatbot — Flujo Impresoras
- [ ] Nueva sesión
- [ ] Escribir "impresora" → responde con opciones

### 3.10 Chatbot — Escalación a Ticket
- [ ] Escribir "crear ticket" → responde ofreciendo escalar
- [ ] Escribir "escalar: Mi monitor se apagó y no enciende"
- [ ] Responde: "Tu conversación se ha escalado al ticket **TK-2026-NNNNN**"
- [ ] Ir a /portal/tickets → verificar nuevo ticket en la lista
- [ ] Ver el ticket → descripción contiene el historial de chat

### 3.11 Chatbot — Fallback (sin LLM key)
- [ ] Escribir algo random: "mesa rota en la sala de reuniones"
- [ ] Responde: "No encontré un flujo específico... escribe 'crear ticket'"

---

## PARTE 4 — Notificaciones

### 4.1 Verificar en log (MAIL_MAILER=log)
```bash
# Después de crear/asignar/comentar tickets:
grep -c "TicketCreated\|TicketAssigned\|TicketCommented\|SatisfactionSurvey" storage/logs/laravel.log
```
- [ ] Al crear ticket → email "Ticket creado" al solicitante
- [ ] Al asignar ticket → email "Ticket asignado" al agente
- [ ] Al comentar (agente → solicitante) → email "Nuevo comentario"
- [ ] Al cerrar ticket → email "¿Cómo fue tu experiencia?" con link de encuesta

### 4.2 Verificar en base de datos (canal database)
```bash
php artisan tinker --execute "echo App\Models\User::find(4)->unreadNotifications->count() . ' notificaciones no leidas';"
```
- [ ] El usuario tiene notificaciones de tipo ticket_created, ticket_commented

---

## PARTE 5 — Inventario (Web Scan)

### 5.1 Verificar collector JS
- [ ] Visitar /portal/tickets como usuario logueado
- [ ] Abrir DevTools → Network → buscar request a /api/inventory/web-scan
- [ ] Debería enviar POST con: hostname, os_name, cpu_cores, ram_gb, gpu_info, etc.
- [ ] Verificar en BD:
```bash
php artisan tinker --execute "echo App\Models\AssetScan::count() . ' scans';"
```

---

## PARTE 6 — SLA (requiere espera o simulación)

### 6.1 Verificar SLA attachment
```bash
php artisan tinker --execute "echo App\Models\Ticket::whereNotNull('sla_config_id')->count() . ' tickets con SLA';"
```
- [ ] Los tickets con departamento asignado tienen sla_config_id, first_response_due_at, resolution_due_at

### 6.2 Simular breach (opcional)
```bash
# Correr check breaches manualmente
php artisan tinker --execute "echo app(App\Services\SlaService::class)->checkBreaches() . ' escalaciones';"
```
- [ ] Si hay tickets con SLA vencido, se crean escalation_logs

### 6.3 Auto-close (opcional)
```bash
# Simular ticket resuelto hace 8 días
php artisan tinker --execute "
  App\Models\Ticket::where('status', 'resuelto')->update(['resolved_at' => now()->subDays(8)]);
  dispatch(new App\Jobs\AutoCloseTicketsJob());
  echo App\Models\Ticket::where('status', 'cerrado')->count() . ' cerrados';
"
```

---

## PARTE 7 — Verificación Final

### 7.1 Tests automatizados
```bash
php artisan test --compact
```
- [ ] **53/53 passing** (128 assertions)

### 7.2 Code style
```bash
vendor/bin/pint --test --format agent
```
- [ ] Sin errores

### 7.3 Rutas registradas
```bash
php artisan route:list --except-vendor | grep -c "GET\|POST"
```
- [ ] Todas las rutas del plan existen

---

## Resumen de rutas para testing rápido

| # | URL | Método | Usuario | Qué hacer |
|---|---|---|---|---|
| 1 | /admin/login | GET | admin | Login |
| 2 | /admin | GET | admin | Dashboard |
| 3 | /admin/departments | GET | admin | Ver/Crear/Editar |
| 4 | /admin/categories | GET | admin | Ver/Crear |
| 5 | /admin/assets | GET | admin | Ver inventario |
| 6 | /admin/shield/roles | GET | admin | Ver roles y permisos |
| 7 | /admin/backups | GET | admin | Ver/Ejecutar backup |
| 8 | /admin/sla-report | GET | admin | Ver reporte SLA |
| 9 | /soporte/login | GET | agente | Login |
| 10 | /soporte | GET | agente | Dashboard |
| 11 | /soporte/tickets | GET | agente | Lista con filtros |
| 12 | /soporte/tickets/create | GET | agente | Crear ticket |
| 13 | /soporte/tickets/1 | GET | agente | Ver + Asignar + Resolver |
| 14 | /soporte/tickets/1/edit | GET | agente | Editar ticket |
| 15 | /soporte/kb-articles | GET | agente | CRUD artículos KB |
| 16 | /soporte/ticket-templates | GET | agente | CRUD plantillas |
| 17 | /soporte/canned-responses | GET | agente | CRUD respuestas predefinidas |
| 18 | /login | GET | usuario | Login Fortify |
| 19 | /portal/tickets | GET | usuario | Mis tickets |
| 20 | /portal/tickets/create | GET | usuario | Crear ticket |
| 21 | /portal/tickets/2 | GET | usuario | Ver ticket + comentar |
| 22 | /portal/chatbot | GET | usuario | Chatbot (flujos + escalación) |
| 23 | POST /api/inventory/web-scan | POST | auth:sanctum | Web scan JS automático |
| 24 | POST /api/inventory/agent-scan | POST | Bearer token | Agent PowerShell |

---

## Notas importantes

1. **MAIL_MAILER=log**: los emails no se envían — se guardan en `storage/logs/laravel.log`
2. **LLM_API_KEY vacío**: el chatbot usa fallback cuando no hay API key de OpenRouter
3. **AZURE_CLIENT_ID vacío**: SSO no funciona hasta configurar credenciales Azure
4. **npm run build**: OBLIGATORIO antes de probar el portal (ViteException si no)
5. **Inventario web scan**: se ejecuta 1 vez por día (localStorage). Borrar localStorage para forzar reescaneo

---

*Documento actualizado el 15 de abril de 2026 — post pruebas Playwright.*

---

## PARTE 8 — Escenarios TestSprite (automatizados por rol)

Esta sección define los escenarios para TestSprite MCP. Cada escenario debe ser auto-contenido (hacer login desde cero y cerrar sesión al final si aplica), porque TestSprite ejecuta los tests en paralelo con sesiones aisladas.

### Credenciales para TestSprite

| Rol | Email | Password | Panel |
|---|---|---|---|
| super_admin | admin@confipetrol.local | password | /admin + /soporte + /portal |
| supervisor_soporte | supervisor@confipetrol.local | password | /soporte |
| agente_soporte | agente@confipetrol.local | password | /soporte |
| usuario_final | usuario@confipetrol.local | password | /portal |

---

### 8.1 Escenarios como super_admin (admin@confipetrol.local)

#### TS-A01 — Admin login y dashboard
1. Navegar a `/admin/login`
2. Llenar email: `admin@confipetrol.local`, password: `password`
3. Submit
4. **Assert:** URL contiene `/admin` (sin `/login`)
5. **Assert:** página muestra estadísticas "Tickets abiertos", "Total tickets", "Usuarios", "KB publicados"
6. **Assert:** menú lateral tiene "Escritorio", "Filament Shield", "Inventario", "Configuración", "Reportes"

#### TS-A02 — Crear departamento y verificar slug auto-generado
1. Login como admin
2. Navegar a `/admin/departments`
3. Click "Crear Departamento"
4. Llenar Nombre: "Logística Test"
5. **Assert:** campo Slug se auto-completa a "logistica-test"
6. Click Guardar
7. **Assert:** aparece notificación de éxito y el departamento en la lista

#### TS-A03 — Crear categoría y asignarla a departamento
1. Login como admin
2. Navegar a `/admin/categories`
3. Click "Crear Categoría"
4. Seleccionar Departamento: "TI"
5. Nombre: "Test Category"
6. **Assert:** Slug auto-generado "test-category"
7. Guardar
8. **Assert:** categoría aparece en la lista agrupada bajo "Department: TI"

#### TS-A04 — Ver reporte SLA
1. Login como admin
2. Navegar a `/admin/sla-report`
3. **Assert:** tabla "Cumplimiento SLA por departamento (últimos 30 días)" visible
4. **Assert:** tabla tiene 5 filas de departamentos y 5 columnas de prioridades
5. **Assert:** al menos una celda muestra "100%" o "—"

#### TS-A05 — Gestión de roles (Shield)
1. Login como admin
2. Navegar a `/admin/shield/roles`
3. **Assert:** lista muestra 7 roles: super_admin, admin, supervisor_soporte, agente_soporte, tecnico_campo, editor_kb, usuario_final
4. Click en "super_admin"
5. **Assert:** página detalle con permisos listados

#### TS-A06 — Acceso triple panel del super_admin
1. Login como admin (panel /admin)
2. Navegar a `/soporte`
3. **Assert:** accede correctamente (admin tiene permiso de soporte)
4. Navegar a `/portal/tickets`
5. **Assert:** accede correctamente

---

### 8.2 Escenarios como supervisor_soporte (supervisor@confipetrol.local)

#### TS-S01 — Supervisor login al panel soporte
1. Navegar a `/soporte/login`
2. Login supervisor@confipetrol.local / password
3. **Assert:** URL `/soporte`
4. **Assert:** dashboard muestra "SLA Compliance", "Tickets abiertos", "Asignados a mí"

#### TS-S02 — Supervisor bloqueado en /admin
1. Login supervisor
2. Navegar a `/admin`
3. **Assert:** 403 Forbidden (supervisor NO tiene rol admin)

#### TS-S03 — Supervisor ve todos los tickets del equipo
1. Login supervisor
2. Navegar a `/soporte/tickets`
3. **Assert:** lista muestra tickets de múltiples agentes, no solo "asignados a mí"

---

### 8.3 Escenarios como agente_soporte (agente@confipetrol.local)

#### TS-G01 — Agente login y dashboard
1. Login agente
2. **Assert:** URL `/soporte`
3. **Assert:** badge "Tickets" en el menú lateral

#### TS-G02 — Agente asigna ticket y hace primera respuesta
1. Login agente
2. Navegar a `/soporte/tickets/1`
3. Click "Asignar" → seleccionar "Agente Soporte" → confirmar
4. **Assert:** notificación "Ticket asignado"
5. **Assert:** estado cambia a "Asignado"
6. Click "Marcar primera respuesta" → confirmar
7. **Assert:** estado cambia a "En progreso"

#### TS-G03 — Agente resuelve y cierra ticket
1. Login agente
2. Navegar a un ticket "En progreso"
3. Click "Resolver" → confirmar
4. **Assert:** estado "Resuelto"
5. Click "Cerrar" → confirmar
6. **Assert:** estado "Cerrado"

#### TS-G04 — Agente reabre ticket cerrado
1. Login agente
2. Ir a lista `/soporte/tickets`, quitar filtro "Solo abiertos"
3. Click ticket cerrado
4. Click "Reabrir" → confirmar
5. **Assert:** estado "Reabierto"

#### TS-G05 — Agente agrega comentario público e interno
1. Login agente
2. Ir a ticket abierto
3. Click "Crear" en sección Comentarios
4. Escribir: "Comentario público test"
5. Toggle "Comentario interno" OFF → Guardar
6. **Assert:** comentario aparece sin icono candado
7. Crear otro con toggle interno ON → Guardar
8. **Assert:** aparece con icono candado

#### TS-G06 — Agente crea ticket desde soporte con prioridad auto
1. Login agente
2. Navegar a `/soporte/tickets/create`
3. Llenar Asunto, Descripción, Solicitante, Departamento, Categoría
4. Seleccionar Impacto "Alto" + Urgencia "Alta"
5. **Assert:** campo Prioridad muestra "Crítica" automáticamente
6. Click Crear
7. **Assert:** redirige a vista del ticket con número TK-YYYY-NNNNN

#### TS-G07 — Agente crea artículo de Knowledge Base
1. Login agente
2. Navegar a `/soporte/kb-articles/create`
3. Llenar título, body, categoría, estado=published, visibilidad=public
4. Guardar
5. **Assert:** artículo aparece en `/soporte/kb-articles`

#### TS-G08 — Agente crea respuesta predefinida compartida
1. Login agente
2. Navegar a `/soporte/canned-responses/create`
3. Título: "Test canned"
4. Body: "Respuesta de prueba"
5. Toggle "Compartida" ON
6. Guardar
7. **Assert:** aparece en la lista

#### TS-G09 — Agente crea plantilla de ticket
1. Login agente
2. Navegar a `/soporte/ticket-templates/create`
3. Llenar Nombre, Asunto, Descripción
4. Guardar
5. **Assert:** aparece en `/soporte/ticket-templates`

#### TS-G10 — Agente bloqueado en /admin
1. Login agente
2. Navegar a `/admin`
3. **Assert:** 403 Forbidden

---

### 8.4 Escenarios como usuario_final (usuario@confipetrol.local)

#### TS-U01 — Usuario login (Fortify) y redirección al portal
1. Navegar a `/login`
2. Login usuario@confipetrol.local / password
3. **Assert:** redirige a `/dashboard`
4. Navegar a `/portal` → **Assert:** redirige a `/portal/tickets`

#### TS-U02 — Usuario ve solo sus propios tickets
1. Login usuario
2. Navegar a `/portal/tickets`
3. **Assert:** lista de tickets visible con cards
4. **Assert:** cada ticket tiene solicitante = usuario_final

#### TS-U03 — Usuario crea ticket desde portal con validación
1. Login usuario
2. Navegar a `/portal/tickets/create`
3. Click Submit sin llenar campos
4. **Assert:** errores de validación visibles (asunto, descripción requeridos)
5. Llenar Asunto "Test ticket from user", Descripción "Este es un ticket de prueba creado desde el portal" (mín 10 chars)
6. Seleccionar Categoría, Impacto Medio, Urgencia Media
7. **Assert:** Prioridad calculada "Media"
8. Click Enviar
9. **Assert:** redirige a `/portal/tickets/{id}` con toast "Ticket creado correctamente"

#### TS-U04 — Usuario filtra sus tickets por estado
1. Login usuario
2. Navegar a `/portal/tickets`
3. Seleccionar filtro estado: "Resuelto"
4. **Assert:** solo tickets resueltos visibles
5. Limpiar filtro
6. **Assert:** todos los tickets visibles

#### TS-U05 — Usuario busca tickets
1. Login usuario
2. Navegar a `/portal/tickets`
3. Escribir "correo" en buscador
4. **Assert:** solo tickets que contengan "correo" visibles

#### TS-U06 — Usuario agrega comentario a ticket abierto
1. Login usuario
2. Click en un ticket abierto
3. Escribir comentario en el formulario
4. Click Enviar
5. **Assert:** comentario aparece en el thread con burbuja azul

#### TS-U07 — Usuario NO puede comentar ticket resuelto
1. Login usuario
2. Click en ticket estado "Resuelto"
3. **Assert:** NO hay formulario de comentario
4. **Assert:** texto "Este ticket está Resuelto — no se pueden agregar comentarios"

#### TS-U08 — Usuario NO ve comentarios internos
1. Login usuario
2. Ver TK-2026-00002 (tiene 1 comentario público + 1 interno)
3. **Assert:** solo 1 comentario visible
4. **Assert:** no aparece texto "Nota interna"

#### TS-U09 — Chatbot flujo reset password
1. Login usuario
2. Navegar a `/portal/chatbot`
3. **Assert:** mensaje de bienvenida del bot
4. Escribir "password" → Enviar
5. **Assert:** bot responde con flujo de selección
6. Escribir "1" (Windows) → Enviar
7. **Assert:** bot responde con instrucciones paso a paso

#### TS-U10 — Chatbot flujo VPN
1. Login usuario
2. Navegar a `/portal/chatbot`
3. Escribir "vpn" → Enviar
4. **Assert:** bot responde con instrucciones FortiClient

#### TS-U11 — Chatbot escalación a ticket
1. Login usuario
2. Navegar a `/portal/chatbot`
3. Escribir "crear ticket" → Enviar
4. **Assert:** bot ofrece escalar
5. Escribir "escalar: Mi problema específico"
6. **Assert:** bot responde con número TK-YYYY-NNNNN
7. Navegar a `/portal/tickets`
8. **Assert:** nuevo ticket aparece en la lista

#### TS-U12 — Usuario bloqueado en /admin y /soporte
1. Login usuario
2. Navegar a `/admin` → **Assert:** 403
3. Navegar a `/soporte` → **Assert:** 403 o redirect

#### TS-U13 — Usuario NO accede a tickets ajenos
1. Login usuario
2. Navegar a `/portal/tickets/999` (ID no existe o de otro usuario)
3. **Assert:** 403 Forbidden o 404 Not Found

#### TS-U14 — Usuario actualiza su perfil
1. Login usuario
2. Navegar a `/settings/profile`
3. Cambiar nombre a "Usuario Test Updated"
4. Click Guardar
5. **Assert:** notificación de éxito
6. **Assert:** campo name muestra nuevo valor

#### TS-U15 — Usuario cambia contraseña
1. Login usuario
2. Navegar a `/settings/password`
3. Llenar Current password: "password"
4. New password: "newpassword123"
5. Confirm: "newpassword123"
6. Click Guardar
7. **Assert:** notificación de éxito
8. Logout y login con nueva contraseña → **Assert:** login OK

---

### 8.5 Escenarios de seguridad (no autenticado)

#### TS-SEC01 — Rutas protegidas sin sesión
1. Sin login, navegar a `/portal/tickets` → **Assert:** redirect a `/login`
2. Navegar a `/admin` → **Assert:** redirect a `/admin/login`
3. Navegar a `/soporte` → **Assert:** redirect a `/soporte/login`
4. Navegar a `/dashboard` → **Assert:** redirect a `/login`

#### TS-SEC02 — Login con credenciales inválidas
1. Navegar a `/login`
2. Llenar fake@test.com / wrong
3. Submit
4. **Assert:** error "Estas credenciales no coinciden con nuestros registros"

#### TS-SEC03 — Registro deshabilitado
1. Navegar a `/register`
2. **Assert:** 404 Not Found (registro deshabilitado — app usa Azure AD SSO)

---

*Sección 8 agregada el 2026-04-16 para soporte TestSprite MCP automatizado.*
