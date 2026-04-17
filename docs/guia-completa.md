# Helpdesk Confipetrol — Guía Completa

**Versión:** 1.5 (módulo usuarios, scope depto, traslado tickets)  
**Fecha:** 17 de abril de 2026  
**Responsable:** Luis Oviedo (luis.oviedo@confipetrol.com)

---

## Qué es

Una plataforma interna de mesa de ayuda que reemplaza GLPI. Centraliza solicitudes de soporte de todas las áreas de Confipetrol (TI, RRHH, Compras, Mantenimiento, Operaciones) en una sola herramienta.

## 3 interfaces, 3 audiencias

| Ruta | Tecnología | Audiencia |
|---|---|---|
| `/admin` | Filament 5 (Amber) | Administradores TI |
| `/soporte` | Filament 5 (Sky) | Agentes y técnicos |
| `/portal` | Livewire 4 + Flux 2 | Usuarios finales |
| `/api` | Sanctum | Agente PowerShell inventario |

---

## 1. Sistema de Tickets

### Creación

El usuario final entra a `/portal/tickets/create`, llena asunto, descripción, categoría, impacto y urgencia, y puede adjuntar hasta 5 archivos (imágenes, PDF, Office, ZIP).

La **prioridad se calcula automáticamente** con la matriz ITIL Impacto × Urgencia:

| Impacto \ Urgencia | Baja | Media | Alta |
|---|---|---|---|
| **Bajo** | Planificada | Baja | Media |
| **Medio** | Baja | Media | Alta |
| **Alto** | Media | Alta | **Crítica** |

Se genera un número único `TK-2026-00001` (atómico, sin colisiones, reinicia cada año). Se envía notificación por email al solicitante confirmando la creación.

### Ciclo de vida

```
Nuevo → Asignado → En progreso → Resuelto → Cerrado
                                     ↑          |
                               Reabierto ←──────┘

              Pendiente cliente (pausa el reloj SLA)
```

Cada transición se hace desde el panel `/soporte` con botones de acción:

- **Asignar**: selecciona un agente, el ticket pasa a "Asignado", se notifica al agente.
- **Marcar primera respuesta**: registra el timestamp, pasa a "En progreso".
- **Resolver**: marca resolved_at.
- **Cerrar**: marca closed_at + dispara encuesta de satisfacción.
- **Reabrir**: limpia timestamps de resolución/cierre.

### Comentarios

- Agentes pueden dejar comentarios **públicos** (visibles al solicitante) o **internos** (solo agentes).
- El primer comentario público de un agente marca automáticamente la "primera respuesta" del SLA.
- Notificaciones bidireccionales: agente comenta → notifica solicitante, y viceversa.

### Adjuntos

- Tickets y comentarios soportan archivos vía Spatie Media Library.
- Formatos: imágenes, PDF, Word, Excel, CSV, ZIP, RAR (máx 10 MB por archivo).

---

## 2. Motor SLA

### Configuración

Cada combinación **departamento × prioridad** tiene un tiempo máximo definido para primera respuesta y resolución. Los tiempos se calculan en **horario laboral** de Bogotá: Lunes a Viernes, 08:00–18:00.

| Prioridad | Primera respuesta | Resolución |
|---|---|---|
| Crítica | 30 min | 4 horas |
| Alta | 1 hora | 8 horas (1 día) |
| Media | 2 horas | 20 horas (2 días) |
| Baja | 4 horas | 40 horas (4 días) |
| Planificada | 8 horas | 100 horas (10 días) |

### Monitoreo automático

Un job programado cada 5 minutos (solo días hábiles) revisa todos los tickets abiertos y genera alertas a 3 niveles:

- **70%** del tiempo → warning
- **90%** del tiempo → warning urgente
- **100%** → breach (SLA vencido)

### Pausa del reloj

Cuando un ticket pasa a "Pendiente cliente", el reloj SLA se pausa. Al retomar, se restan los minutos pausados.

### Auto-cierre

Un job diario a las 6am cierra automáticamente tickets que llevan más de 7 días en "Resuelto" sin reabrir.

---

## 3. Inventario de PCs

### Doble mecanismo de captura

**Web Scan (automático, limitado):**
- Script JS silencioso que recolecta: OS, CPU cores, RAM estimada, GPU (WebGL), resolución, timezone.
- Se ejecuta una vez al día cuando el usuario visita el portal.
- No requiere instalar nada.

**Agente PowerShell (completo):**
- Script .ps1 instalado en cada equipo Windows.
- Recolecta: hardware, BIOS, serial, discos, software instalado, actualizaciones.
- Autenticado con token Sanctum (Bearer).
- Sincroniza la lista completa de software en cada scan.

### Datos almacenados

- **Asset**: hostname, serial, fabricante, modelo, OS, CPU, RAM, disco, GPU, IP, MAC, estado.
- **Software por activo**: nombre, versión, publisher, fecha de instalación.
- **Componentes**: CPU, RAM, disco, GPU, periféricos (con specs JSON flexibles).
- **Historial**: cada cambio y scan queda registrado.
- **Scans crudos**: payload completo en JSON para auditoría.

---

## 4. Base de Conocimiento (KB)

### Estructura

- **Categorías KB** jerárquicas (padre/hijo).
- **Artículos** con título, cuerpo, categoría, autor, tags.
- **Estados**: borrador → publicado → obsoleto.
- **Visibilidad**: público, interno (agentes), grupo.
- Contadores: vistas, útil, no útil.

### Versionado

Cada edición crea un snapshot de la versión anterior con: número de versión, editor, título, cuerpo y resumen del cambio.

### Feedback

Los usuarios marcan "útil" o "no útil" (1 voto por usuario) con comentario opcional.

---

## 5. Chatbot Híbrido

### Pipeline de procesamiento

Cuando un usuario escribe en `/portal/chatbot`:

1. **¿Quiere escalar?** (crear ticket / hablar con agente) → Ofrece crear ticket.
2. **¿Hay un flujo activo?** → Continúa paso a paso.
3. **Clasificar intent** (keywords vs triggers) → Si match ≥ 30%, inicia flujo.
4. **Buscar en KB** (RAG con embeddings) → Si encuentra artículos, genera respuesta con LLM.
5. **LLM sin contexto** → Respuesta general.
6. **Fallback** → "¿Quieres crear un ticket?"

### Flujos predefinidos

| Flujo | Triggers |
|---|---|
| Reset de contraseña | contraseña, password, clave, olvidé... |
| Configurar VPN | vpn, remoto, desde casa... |
| Impresoras | impresora, imprimir, no imprime... |

### Escalación

El usuario escribe `escalar: Mi impresora no funciona` y el chatbot crea un ticket con toda la conversación como descripción.

---

## 6. RAG + LLM (Inteligencia Artificial)

### Cómo funciona

1. Al publicar un artículo KB, un job lo divide en párrafos y genera embeddings vectoriales.
2. Cuando el usuario pregunta, su pregunta se vectoriza y se compara con los artículos.
3. Se seleccionan los 3 más relevantes (similitud coseno) y se arma un prompt.
4. El LLM genera una respuesta basada en información real de Confipetrol.

### Proveedores

- **OpenRouter** (default, gratis para pruebas) — modelo Llama 3.1.
- **Anthropic Claude** (cuando se apruebe) — solo cambiar 3 variables en .env.

---

## 7. Reportes y Dashboards

### Dashboard Admin (`/admin`)

- 5 stats: tickets abiertos (con breach), total histórico, usuarios + activos, KB publicados, CSAT.
- Gráfico doughnut: tickets por estado.
- Gráfico líneas: creados vs resueltos (30 días).

### Dashboard Soporte (`/soporte`)

- 4 stats operativos: abiertos, sin triage, alta/crítica, asignados a mí (refresh 30s).
- 3 stats SLA: compliance %, tiempo promedio primera respuesta, CSAT.

### Reporte SLA (`/admin/sla-report`)

- Matriz departamento × prioridad con % compliance color-coded.
- Tabla de últimas 20 escalaciones.

### Exportación Excel

Seleccionar registros en Tickets o Assets y click "Exportar Excel".

---

## 8. Plantillas y Respuestas Predefinidas

### Plantillas de ticket (`/soporte/ticket-templates`)

Formularios pre-llenados que el agente aplica al **crear** un ticket repetitivo.

**Caso de uso:** un agente recibe "Necesito equipo para Juan Pérez" cientos de veces al año. En lugar de escribir asunto/descripción/categoría desde cero, aplica la plantilla "Solicitud de equipo nuevo" y solo modifica los datos del empleado.

Campos guardados en la plantilla:
- Nombre interno (identificador para el agente)
- Asunto pre-llenado
- Descripción pre-llenada (con placeholders tipo `[NOMBRE]`)
- Categoría, Impacto, Urgencia por defecto
- Activa / inactiva

**Scope por depto:** las plantillas que el agente ve en el selector se filtran automáticamente a su departamento.

### Respuestas predefinidas / Canned responses (`/soporte/canned-responses`)

Fragmentos de texto que el agente **pega al comentar** un ticket para ahorrar tipeo repetitivo.

**Caso de uso:** un agente cierra 20 tickets al día con el mismo mensaje de "gracias por tu paciencia, si vuelve el problema reabre el ticket". Lo guarda una vez como canned response y lo inserta con un click.

Campos:
- Título (cómo se identifica en la lista)
- Body (el texto que se inserta, con Markdown)
- Categoría (scoped al depto)
- Toggle "Compartida": ON = todo el depto la usa; OFF = solo el autor.

### Diferencia clave

| | Plantilla | Canned Response |
|---|---|---|
| Momento de uso | Al CREAR un ticket | Al COMENTAR un ticket |
| Qué rellena | Formulario completo | Body del comentario |
| Bulk delete | Supervisor/admin | Supervisor/admin |

---

## 9. Encuestas de Satisfacción

- Al cerrar un ticket se envía email con link one-time (token 64 chars).
- Calificación 1-5 estrellas + comentario opcional.
- Resultados en dashboards como CSAT promedio.

---

## 10. Historial de Cambios

- Cada cambio en un ticket (status, priority, assigned_to, etc.) se registra con Spatie Activity Log.
- Solo campos que cambiaron + usuario + timestamp.

---

## 11. SSO Azure AD (Entra ID)

### Flujo

1. Usuario va a `/auth/azure`.
2. Microsoft autentica.
3. El sistema crea/actualiza usuario, sincroniza departamento y rol desde grupos Azure.
4. Redirige al panel correcto según rol.

### Configuración

- Credenciales en `.env`: AZURE_CLIENT_ID, AZURE_CLIENT_SECRET, AZURE_TENANT_ID.
- Mapeo de grupos en `config/azure-roles.php`.

---

## 12. Roles y Permisos

### Roles (7)

| Rol | Panel | Descripción | Scope de visibilidad |
|---|---|---|---|
| super_admin | /admin + /soporte + /portal | Control total. Puede todo. | Todo, sin filtros |
| admin | /admin + /soporte | Administración funcional | Todo, sin filtros |
| supervisor_soporte | /soporte | Supervisa grupo de su depto | Todos los tickets de su depto |
| agente_soporte | /soporte | Atiende tickets | Solo sus asignados + sin asignar de su depto |
| tecnico_campo | /soporte | Técnicos en sitio | Igual que agente |
| editor_kb | /soporte | Gestiona KB | KB de su depto |
| usuario_final | /portal | Crea y consulta sus tickets | Solo sus propios tickets |

### Diferencias supervisor vs agente (v1.4+)

| Capacidad | Supervisor | Agente |
|---|:---:|:---:|
| Ver todos los tickets del depto | ✅ | ❌ (solo sus asignados + sin asignar) |
| Crear ticket | ✅ | ✅ |
| Editar ticket | ✅ | ✅ |
| Eliminar ticket | ✅ | ❌ |
| Trasladar ticket a otro depto | ✅ | ❌ |
| Crear agentes (en /soporte/users) | ✅ (solo de su depto) | ❌ |
| Publicar KB (pasar de Borrador a Publicado) | ✅ | ❌ (solo crea Borrador) |
| Bulk delete en plantillas/canned | ✅ | ❌ |
| Total permisos | 55 | 27 |

### Gestión de usuarios

**Super_admin (`/admin/users`):** CRUD de cualquier usuario, elige rol y depto libremente.

**Supervisor (`/soporte/users`):** crea solo agentes y **forzadamente en su propio depto**. Este diseño asegura que un supervisor no pueda crear agentes en departamentos ajenos.

### Traslado de tickets

Cuando un ticket se crea con categoría equivocada (ej: un tema de nómina cae en TI), el supervisor tiene un botón **"Trasladar a otro depto."** en la vista del ticket. Al trasladarlo:

- Se cambia `department_id` al destino correcto.
- Se resetea `assigned_to_id` y `category_id` para que el nuevo equipo haga re-triage.
- Se envía email al solicitante con el motivo del traslado.
- Solo disponible para supervisor/admin y en tickets abiertos.

---

## 13. Stack Técnico

| Componente | Tecnología |
|---|---|
| Backend | Laravel 13, PHP 8.5 |
| Admin/Soporte | Filament 5.5 + 9 plugins |
| Portal | Livewire 4 + Flux 2 |
| CSS | Tailwind 4 |
| Auth | Fortify (2FA) + Socialite (Azure SSO) |
| API | Sanctum |
| RBAC | Spatie Permission + Filament Shield |
| Adjuntos | Spatie Media Library |
| Auditoría | Spatie Activity Log |
| Backup | Spatie Backup |
| Exportación | pxlrbt/filament-excel |
| LLM | OpenRouter / Claude |
| BD | MySQL 8 (InnoDB) |
| Tests | Pest 4 (53 tests, 128 assertions) |

---

## 14. Cuentas de prueba

Todos con password `password`.

| Email | Rol | Depto |
|---|---|---|
| admin@confipetrol.local | super_admin | — |
| supervisor@confipetrol.local | supervisor_soporte | TI |
| agente@confipetrol.local | agente_soporte | TI |
| supervisor.rrhh@confipetrol.local | supervisor_soporte | RRHH |
| agente.rrhh@confipetrol.local | agente_soporte | RRHH |
| usuario@confipetrol.local | usuario_final | Operaciones |

Los 2 pares TI/RRHH permiten demostrar el aislamiento por departamento: el supervisor/agente de TI no ve tickets de RRHH y viceversa.

---

## 15. Repositorio

**GitHub:** https://github.com/logo3x/helpdesk

---

*Documento actualizado 2026-04-17 — v1.5.*
