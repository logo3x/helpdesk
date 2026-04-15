# Plan de Testing — Helpdesk Confipetrol

**Fecha:** 15 de abril de 2026
**Versión:** 1.1 (post-pruebas Playwright)

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
- [ ] Tabla vacía inicialmente
- [ ] Click "Crear"
- [ ] Llenar título, slug (autogenerado), body, categoría
- [ ] Estado: "published", Visibilidad: "public"
- [ ] Guardar → aparece en lista
- [ ] Editar → cambiar algo → Guardar

### 2.12 Plantillas (/soporte/ticket-templates)
- [ ] Click "Crear"
- [ ] Nombre: "Solicitud de equipo"
- [ ] Asunto + Descripción predefinidos
- [ ] Guardar → aparece en lista

### 2.13 Respuestas Predefinidas (/soporte/canned-responses)
- [ ] Click "Crear"
- [ ] Título: "Ticket recibido"
- [ ] Body: "Hemos recibido tu solicitud. Un agente te contactará pronto."
- [ ] Toggle "Compartida" ON
- [ ] Guardar → aparece en lista

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
