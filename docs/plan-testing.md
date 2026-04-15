# Plan de Testing — Helpdesk Confipetrol

**Prerequisitos:**
```bash
php artisan migrate:fresh --seed --force
npm run build
php artisan serve
```

**Credenciales:**
| Email | Password | Rol |
|---|---|---|
| admin@confipetrol.local | password | super_admin |
| agente@confipetrol.local | password | agente_soporte |
| supervisor@confipetrol.local | password | supervisor_soporte |
| usuario@confipetrol.local | password | usuario_final |

---

## PARTE 1 — Panel Admin (/admin)

### 1.1 Login y Dashboard
1. Ir a http://127.0.0.1:8000/admin/login
2. Ingresar: admin@confipetrol.local / password
3. [ ] Verificar que carga el dashboard
4. [ ] Verificar 5 stats: Tickets abiertos (2), Total tickets (3), Usuarios (4), KB publicados (0), CSAT (—)
5. [ ] Verificar gráfico "Tickets creados (últimos 30 días)" con líneas creados/resueltos
6. [ ] Verificar gráfico "Tickets por estado" (doughnut)

### 1.2 Departamentos
1. Ir a /admin/departments
2. [ ] Verificar 5 departamentos listados (TI, RRHH, Compras, Mantenimiento, Operaciones)
3. [ ] Click "Crear Departamento"
4. [ ] Llenar: Nombre = "Logística", verificar que el slug se autogenera como "logistica"
5. [ ] Guardar → verificar que aparece en la lista
6. [ ] Editar "Logística" → cambiar descripción → Guardar
7. [ ] Verificar columna "Usuarios" muestra conteo

### 1.3 Categorías
1. Ir a /admin/categories
2. [ ] Verificar 19 categorías agrupadas por departamento
3. [ ] Click "Crear Categoría"
4. [ ] Seleccionar departamento "Logística", Nombre = "Despachos"
5. [ ] Guardar → verificar que aparece bajo el grupo "Logística"
6. [ ] Verificar filtro por departamento funciona
7. [ ] Verificar filtro activas/inactivas funciona

### 1.4 Inventario
1. Ir a /admin/assets
2. [ ] Verificar que la tabla está vacía (o tiene 1 si ya visitaste el portal)
3. [ ] Verificar que el botón "Exportar Excel" aparece en acciones bulk

### 1.5 Shield → Roles
1. Ir a /admin/shield/roles
2. [ ] Verificar que se listan los 7 roles
3. [ ] Click en "super_admin" → verificar todos los permisos marcados
4. [ ] Click en "agente_soporte" → verificar permisos de Ticket, KB, Template, CannedResponse

### 1.6 Respaldos
1. Ir a /admin/backups
2. [ ] Verificar que se muestra el dashboard de Spatie Backup
3. [ ] Click "Create backup" (solo DB)
4. [ ] Verificar que se genera un archivo en storage/app/backups/

### 1.7 Reporte SLA
1. Ir a /admin/sla-report
2. [ ] Verificar matriz 5 departamentos × 5 prioridades
3. [ ] Verificar que RRHH/Baja muestra "100%" (1 ticket resuelto sin breach)
4. [ ] Verificar sección "Últimas escalaciones" (vacía si no ha pasado tiempo)

---

## PARTE 2 — Panel Soporte (/soporte)

### 2.1 Login y Dashboard
1. Cerrar sesión del admin
2. Ir a http://127.0.0.1:8000/soporte/login
3. Ingresar: agente@confipetrol.local / password
4. [ ] Verificar dashboard con 7 stats
5. [ ] SLA Compliance: 100%
6. [ ] Tickets abiertos: 2
7. [ ] Sin asignar/reabiertos: 1
8. [ ] Asignados a mí: 1

### 2.2 Lista de Tickets
1. Ir a /soporte/tickets
2. [ ] Verificar filtro "Solo abiertos" activo por defecto
3. [ ] Verificar 2 tickets visibles (TK-2026-00001 Nuevo, TK-2026-00002 En progreso)
4. [ ] Verificar badge "1" en el menú lateral (tickets sin asignar)
5. [ ] Verificar columnas: Número, Asunto, Estado, Prioridad, Solicitante, Asignado a
6. [ ] Click en icono de filtros → verificar filtros disponibles (estado, prioridad, departamento, etc.)
7. [ ] Activar filtro "Asignados a mí" → verificar solo 1 ticket (TK-2026-00002)

### 2.3 Ver Ticket (ViewTicket)
1. Click en "Ver" en TK-2026-00002
2. [ ] Verificar datos: número, asunto, estado "En progreso", prioridad "Crítica"
3. [ ] Verificar botones de acción visibles: "Resolver" (visible), "Cerrar" (no visible aún)
4. [ ] Verificar sección "Comentarios" con 2 comentarios existentes (1 público, 1 interno con candado)

### 2.4 Agregar Comentario
1. En la vista de TK-2026-00002, sección Comentarios
2. [ ] Click "New" / "Crear"
3. [ ] Escribir "Revisé los logs, el problema es DNS" en el cuerpo
4. [ ] Dejar "Comentario interno" en OFF
5. [ ] Guardar → verificar que aparece en la lista
6. [ ] Crear otro comentario con "Nota: escalar si no se resuelve hoy" con toggle "interno" ON
7. [ ] Verificar que muestra candado en el comentario interno

### 2.5 Asignar Ticket
1. Ir a /soporte/tickets, click "Ver" en TK-2026-00001 (Nuevo, sin asignar)
2. [ ] Click botón "Asignar"
3. [ ] Seleccionar "Agente Soporte" en el dropdown
4. [ ] Confirmar → verificar que el estado cambia a "Asignado"
5. [ ] Verificar en la lista que ahora muestra "Agente Soporte" en columna "Asignado a"

### 2.6 Resolver y Cerrar Ticket
1. Ver TK-2026-00001 (ahora Asignado)
2. [ ] Click "Marcar primera respuesta" → verificar estado cambia a "En progreso"
3. [ ] Click "Resolver" → confirmar → verificar estado "Resuelto"
4. [ ] Click "Cerrar" → confirmar → verificar estado "Cerrado"
5. [ ] Verificar que desaparece de la lista (filtro "Solo abiertos")
6. [ ] Quitar filtro → verificar que el ticket aparece como "Cerrado"

### 2.7 Reabrir Ticket
1. Ver el ticket cerrado TK-2026-00001
2. [ ] Click "Reabrir" → confirmar
3. [ ] Verificar estado "Reabierto"
4. [ ] Verificar que vuelve a aparecer en la lista con filtro "Solo abiertos"

### 2.8 Crear Ticket desde Soporte
1. Click "Crear Ticket"
2. [ ] Llenar Asunto: "Prueba desde soporte"
3. [ ] Llenar Descripción: "Ticket creado por agente a nombre de usuario"
4. [ ] Seleccionar Solicitante: "Usuario Final"
5. [ ] Seleccionar Impacto: "Alto", Urgencia: "Alta"
6. [ ] Verificar que Prioridad muestra "Crítica" automáticamente
7. [ ] Seleccionar Departamento y Categoría
8. [ ] Guardar → verificar que se crea con número TK-2026-00004
9. [ ] Verificar que redirige a la vista del ticket

### 2.9 Base de Conocimiento
1. Ir a /soporte/kb-articles
2. [ ] Verificar tabla vacía (no hay artículos aún)
3. [ ] Click "Crear"
4. [ ] Título: "Cómo conectarse al WiFi corporativo"
5. [ ] Slug: verificar que se autogenera
6. [ ] Body: escribir instrucciones
7. [ ] Estado: "published"
8. [ ] Guardar → verificar que aparece en la lista

### 2.10 Plantillas
1. Ir a /soporte/ticket-templates
2. [ ] Click "Crear"
3. [ ] Nombre: "Solicitud de equipo nuevo"
4. [ ] Asunto: "Solicitud de equipo - [nombre]"
5. [ ] Descripción: texto predefinido
6. [ ] Guardar → verificar en lista

### 2.11 Respuestas Predefinidas
1. Ir a /soporte/canned-responses
2. [ ] Click "Crear"
3. [ ] Título: "Ticket recibido"
4. [ ] Body: "Hemos recibido tu solicitud y estamos trabajando en ella."
5. [ ] Compartida: ON
6. [ ] Guardar → verificar en lista

### 2.12 Exportar Excel
1. Ir a /soporte/tickets
2. [ ] Seleccionar todos los tickets (checkbox header)
3. [ ] Click "Acciones" → "Exportar Excel"
4. [ ] Verificar que se descarga un .xlsx

---

## PARTE 3 — Portal de Usuario (/portal)

### 3.1 Login
1. Cerrar sesión
2. Ir a http://127.0.0.1:8000/login
3. Ingresar: usuario@confipetrol.local / password
4. [ ] Verificar que redirige a /dashboard
5. [ ] Navegar a /portal/tickets

### 3.2 Mis Tickets
1. En /portal/tickets
2. [ ] Verificar 3 tickets listados con badges de color
3. [ ] Verificar buscador: escribir "correo" → solo muestra TK-2026-00002
4. [ ] Verificar filtro por estado: seleccionar "Resuelto" → solo TK-2026-00003
5. [ ] Verificar nav header: "Crear ticket", "Mis tickets", "Asistente"

### 3.3 Crear Ticket
1. Click "Crear ticket" o ir a /portal/tickets/create
2. [ ] Llenar Asunto: "No puedo acceder al sistema de nómina" (mín 5 chars)
3. [ ] Llenar Descripción: "Al intentar entrar muestra error 403..." (mín 10 chars)
4. [ ] Seleccionar Categoría: "Nómina"
5. [ ] Cambiar Impacto a "Alto", Urgencia a "Media"
6. [ ] Verificar que "Prioridad calculada" muestra "Alta"
7. [ ] (Opcional) Adjuntar un archivo de prueba
8. [ ] Click "Enviar ticket"
9. [ ] Verificar redirect a la vista del ticket con número TK-2026-00004 o 00005
10. [ ] Verificar toast "Ticket creado correctamente"

### 3.4 Ver Ticket
1. Click en TK-2026-00002 desde la lista
2. [ ] Verificar header: número, estado "En progreso", prioridad "Crítica"
3. [ ] Verificar descripción visible
4. [ ] Verificar que los adjuntos se muestran (si los hay)
5. [ ] Verificar comentarios públicos visibles (NO los internos)
6. [ ] Verificar que NO aparece el comentario con candado del supervisor

### 3.5 Agregar Comentario
1. En la vista de TK-2026-00002
2. [ ] Escribir "Sigue sin funcionar el correo" en el campo de comentario
3. [ ] Click "Enviar comentario"
4. [ ] Verificar que aparece con burbuja azul (es del solicitante)
5. [ ] Verificar timestamp "hace unos segundos"

### 3.6 Ticket Cerrado — No Comentar
1. Ver TK-2026-00003 (Resuelto)
2. [ ] Verificar que el formulario de comentario NO aparece
3. [ ] Verificar mensaje "Este ticket está Resuelto — no se pueden agregar comentarios"

### 3.7 Seguridad — No ver tickets ajenos
1. Intentar acceder a un ticket que NO sea del usuario (si hay TK de otro usuario)
2. [ ] Verificar que da error 403 Forbidden

### 3.8 Chatbot — Flujos
1. Ir a /portal/chatbot
2. [ ] Verificar mensaje de bienvenida del asistente
3. [ ] Escribir "password" → verificar que activa flujo de reset de contraseña
4. [ ] Escribir "1" → verificar que avanza al siguiente paso
5. [ ] Escribir "sí" → verificar que el flujo se completa

### 3.9 Chatbot — VPN
1. Recargar /portal/chatbot (nueva sesión después de 30 min o reload)
2. [ ] Escribir "vpn" → verificar que activa flujo VPN
3. [ ] Verificar que muestra pasos de FortiClient

### 3.10 Chatbot — Escalación
1. En el chatbot
2. [ ] Escribir "crear ticket" → verificar que ofrece escalar
3. [ ] Escribir "escalar: Mi impresora no funciona" → verificar que crea ticket
4. [ ] Verificar mensaje con número de ticket creado
5. [ ] Ir a /portal/tickets → verificar que el nuevo ticket aparece

---

## PARTE 4 — Notificaciones

### 4.1 Verificar emails en log
1. Después de crear/asignar/comentar tickets:
```bash
cat storage/logs/laravel.log | grep -A 5 "Ticket creado\|Ticket asignado\|Nuevo comentario"
```
2. [ ] Verificar que se generaron notificaciones de tipo mail
3. [ ] Verificar que la tabla `notifications` tiene registros (canal database)
```bash
php artisan tinker --execute "echo App\Models\User::find(4)->notifications()->count();"
```

---

## PARTE 5 — Verificación Final

### 5.1 Tests automatizados
```bash
php artisan test --compact
```
- [ ] 53/53 passing

### 5.2 Pint
```bash
vendor/bin/pint --test --format agent
```
- [ ] Sin errores de estilo

### 5.3 Rutas
```bash
php artisan route:list --except-vendor | wc -l
```
- [ ] Verificar que todas las rutas listadas en la tabla de accesos existen

---

## Notas

- **MAIL_MAILER=log**: los emails no se envían realmente, se escriben en `storage/logs/laravel.log`
- **LLM_API_KEY vacío**: el chatbot usa fallback si no hay key de OpenRouter
- **AZURE_CLIENT_ID vacío**: el botón SSO no funciona hasta configurar las credenciales
- **Inventario web scan**: al visitar el portal, el JS envía datos del browser al API. Verificar en la tabla `asset_scans` después de la primera visita
