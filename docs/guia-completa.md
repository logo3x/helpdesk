# Helpdesk Confipetrol — Guía Completa

**Versión:** 1.6 (integración de plantillas + canned responses + 75 registros demo)  
**Fecha:** 17 de abril de 2026  
**Responsable:** Luis Oviedo (luis.oviedo@confipetrol.com)

---

## Qué es

Una plataforma interna de mesa de ayuda que reemplaza GLPI. Centraliza solicitudes de soporte de todas las áreas de Confipetrol (TI, RRHH, Compras, Mantenimiento, Operaciones) en una sola herramienta.

## 3 interfaces, 3 audiencias

| Ruta | Tecnología | Audiencia |
|---|---|---|
| `/admin` | Filament 5 (Amber) | Administradores TI |
| `/soporte` | Filament 5 (Sky) | Agentes, supervisores y técnicos |
| `/portal` | Livewire 4 + Flux 2 | Usuarios finales |
| `/api` | Sanctum | Agente PowerShell inventario |

---

## 1. Sistema de Tickets

### ¿Para qué sirve?
Centralizar y rastrear **todas** las solicitudes de soporte en Confipetrol con trazabilidad completa (quién, cuándo, qué, estado, tiempos).

### Caso real
El área de nómina necesita un equipo para un nuevo empleado. En lugar de llamar por teléfono a TI o enviar un correo suelto, crean el ticket TK-2026-00123 desde el portal. El ticket pasa por el flujo estándar (Nuevo → Asignado → En progreso → Resuelto → Cerrado) y todos los involucrados saben en qué estado va.

### Cómo funciona

**Creación:**
- Usuario final entra a `/portal/tickets/create`.
- Llena asunto (5-100 chars), descripción, categoría, impacto, urgencia.
- Puede adjuntar hasta 5 archivos (10 MB c/u).
- La **prioridad se calcula automáticamente** con matriz ITIL:

| Impacto \ Urgencia | Baja | Media | Alta |
|---|---|---|---|
| **Bajo** | Planificada | Baja | Media |
| **Medio** | Baja | Media | Alta |
| **Alto** | Media | Alta | **Crítica** |

- Se genera número único `TK-YYYY-NNNNN` (atómico, reinicia cada año).
- Se envía notificación al solicitante confirmando la creación.

**Ciclo de vida:**
```
Nuevo → Asignado → En progreso → Resuelto → Cerrado
                                     ↑          |
                               Reabierto ←──────┘
              Pendiente cliente (pausa el reloj SLA)
```

**Acciones desde `/soporte`:**
- **Asignar** → el ticket pasa a "Asignado" y notifica al agente.
- **Marcar primera respuesta** → registra timestamp, pasa a "En progreso".
- **Resolver** → marca `resolved_at`.
- **Cerrar** → marca `closed_at` + dispara encuesta de satisfacción.
- **Reabrir** → limpia timestamps.
- **Trasladar a otro depto.** (solo supervisor+) → cambia departamento + notifica al solicitante.

**Comentarios:**
- Públicos (visibles al solicitante) o **internos** (solo agentes).
- Al comentar, el agente puede **insertar una respuesta predefinida** con un dropdown.
- El primer comentario público marca "primera respuesta" para SLA.

**Adjuntos:** vía Spatie Media Library. Formatos: imágenes, PDF, Office, CSV, ZIP, RAR.

---

## 2. Motor SLA

### ¿Para qué sirve?
Garantizar que los tickets se atienden en los tiempos contractuales acordados con cada área, con alertas automáticas antes de incumplir.

### Caso real
Un ticket crítico de TI (servidor caído que afecta a 50 usuarios) tiene SLA de 30 min para primera respuesta y 4 horas para resolución. Cuando lleva 21 min sin atender, el sistema alerta al supervisor (70%). Si llega a 27 min (90%) se escala al jefe de TI. Si no se resuelve en 30 min, se registra breach en el reporte gerencial.

### Configuración

Cada combinación **departamento × prioridad** tiene tiempo máximo en **horario laboral de Bogotá** (L-V 08:00–18:00):

| Prioridad | Primera respuesta | Resolución |
|---|---|---|
| Crítica | 30 min | 4 horas |
| Alta | 1 hora | 8 horas |
| Media | 2 horas | 20 horas |
| Baja | 4 horas | 40 horas |
| Planificada | 8 horas | 100 horas |

### Monitoreo automático

Un job cada 5 min (solo días hábiles) revisa tickets abiertos y genera alertas:
- **70%** del tiempo → warning
- **90%** → warning urgente
- **100%** → breach (SLA vencido, queda en `escalation_logs`)

### Pausa del reloj

"Pendiente cliente" pausa el SLA (el usuario no respondió). Al retomar, se restan los minutos pausados.

### Auto-cierre

Job diario 6am cierra tickets con 7+ días en "Resuelto" sin reabrir.

---

## 3. Inventario de PCs

### ¿Para qué sirve?
Tener un registro vivo de **qué hardware y software** está en uso en cada equipo de Confipetrol, con histórico de cambios, para:
- Auditorías de seguridad (software vulnerable, no autorizado)
- Planeación de renovación de equipos (edad, capacidad)
- Licenciamiento (cuántas copias activas de Office, Adobe, etc.)
- Trazabilidad en caso de pérdida/robo

### Caso real
Se detecta una vulnerabilidad crítica en cierta versión de Chrome. El admin de TI consulta el inventario → filtra "Chrome versión < 120.x.x" → obtiene la lista de 47 equipos a actualizar y sus usuarios. Con un click envía correo masivo con instrucciones.

### Doble mecanismo de captura

**Web Scan (automático, limitado):**
- Script JS que recolecta: OS, CPU cores, RAM estimada, GPU (WebGL), resolución, timezone.
- Se ejecuta una vez al día al visitar el portal.
- No requiere instalar nada.

**Agente PowerShell (completo):**
- Script `.ps1` instalado en cada equipo.
- Recolecta: hardware, BIOS, serial, discos, software instalado, actualizaciones.
- Autenticado con token Sanctum Bearer.
- Sincroniza la lista completa de software en cada scan.

### Datos almacenados

- **Asset**: hostname, serial, fabricante, modelo, OS, CPU, RAM, disco, GPU, IP, MAC, estado.
- **Software por activo**: nombre, versión, publisher, fecha de instalación.
- **Componentes**: CPU, RAM, disco, GPU, periféricos (specs JSON).
- **Historial**: cada cambio y scan registrado.
- **Scans crudos**: payload JSON completo para auditoría.

---

## 4. Base de Conocimiento (KB)

### ¿Para qué sirve?
Reducir tickets repetitivos dándole a los usuarios artículos de auto-servicio ("cómo hacer X") y a los agentes una referencia para atender más rápido.

### Caso real
TI recibía 40 tickets al mes de "cómo configurar el correo en el celular". Se publicó UN artículo KB con los pasos. Los usuarios lo encuentran en el chatbot (vía RAG) o buscando directamente. Los tickets bajaron a 8 al mes (–80%).

### Estructura (v1.5)

- **Categoría = Departamento**: el artículo pertenece al depto responsable (TI, RRHH, etc.).
- **Estados**: Borrador → Publicado → Archivado.
- **Visibilidad**: Pública (usuarios finales) o Interna (solo agentes).
- **Author**: quien lo crea.
- **Contadores**: vistas, útil, no útil.

### Flujo de aprobación

1. El **agente** crea el artículo en status **"Borrador"** (no puede publicar directamente).
2. El **supervisor** revisa y pasa a **"Publicado"**.
3. El `published_at` se asigna automáticamente (campo readonly).
4. Los usuarios finales solo ven artículos **Publicados + Públicos**.

### Versionado

Cada edición crea snapshot de la versión anterior (número, editor, título, cuerpo, resumen del cambio).

### Feedback

Los usuarios marcan "útil" / "no útil" con comentario opcional. El agente ve la retroalimentación y puede mejorar el artículo.

### Datos demo (v1.6)

Tras `php artisan migrate:fresh --seed --force` hay **25 artículos** (5 por depto) más los 10 originales = **35 KB publicados**.

---

## 5. Chatbot Híbrido

### ¿Para qué sirve?
Resolver consultas frecuentes sin que el usuario tenga que crear ticket y esperar a un agente. Si el bot no puede resolver, escala automáticamente a ticket con la conversación como contexto.

### Caso real
Un usuario escribe "olvidé mi contraseña de Windows". El chatbot detecta el intent, le muestra los pasos de reset (flujo rule-based). Si el usuario responde "sí, funcionó" cierra la conversación. Si dice "no funciona", el bot escala automáticamente creando el ticket TK-2026-00456 con la transcripción completa para que un agente lo atienda.

### Pipeline de decisión

1. **¿Quiere escalar?** → Ofrece crear ticket.
2. **¿Hay flujo activo?** → Continúa paso a paso.
3. **Clasificar intent** (keywords vs triggers, ≥30% match) → Inicia flujo.
4. **Buscar en KB** (RAG vectorial) → Si hay artículos, genera respuesta con LLM.
5. **LLM sin contexto** → Respuesta general.
6. **Fallback** → "¿Quieres crear un ticket?"

### Flujos predefinidos

| Flujo | Triggers |
|---|---|
| Reset de contraseña | contraseña, password, clave, olvidé |
| Configurar VPN | vpn, remoto, desde casa |
| Impresoras | impresora, imprimir, no imprime |

### Escalación

El usuario escribe `escalar: Mi impresora no funciona` → el chatbot crea un ticket con toda la conversación como descripción.

---

## 6. RAG + LLM (Inteligencia Artificial)

### ¿Para qué sirve?
Que el chatbot responda preguntas basándose en la **documentación real de Confipetrol** (KB) en lugar de inventar respuestas genéricas.

### Caso real
Usuario pregunta "¿cuántos días de vacaciones tengo derecho al año?". El sistema:
1. Vectoriza la pregunta con embeddings.
2. Busca en KB los 3 artículos más similares (cosine similarity).
3. Arma un prompt: "Usando estos 3 artículos de Confipetrol, responde la pregunta".
4. El LLM responde "Tienes 15 días hábiles al año una vez completado el primer año. Ver el artículo 'Cómo solicitar vacaciones' para el proceso."

### Cómo funciona

1. Al publicar artículo KB, un job lo divide en párrafos y genera embeddings.
2. La pregunta se vectoriza.
3. Top-3 artículos por similitud se meten en el contexto del LLM.
4. Respuesta generada solo con info real.

### Proveedores

- **OpenRouter** (default, gratis) — Llama 3.1.
- **Anthropic Claude** — solo cambiar 3 variables en `.env`.

---

## 7. Reportes y Dashboards

### ¿Para qué sirven?
Dar visibilidad en tiempo real del estado del helpdesk a administradores y supervisores, para decisiones operativas (redistribuir carga) y ejecutivas (negociar SLA con áreas).

### Caso real
El jefe de TI ve en `/admin` que hay 12 tickets abiertos con breach de SLA, y el supervisor de soporte ve en `/soporte` que el equipo tiene 50 tickets pendientes con solo 3 agentes activos. Piden refuerzo para la próxima semana.

### Dashboard Admin (`/admin`)

- 5 stats: tickets abiertos (con breach), total histórico, usuarios + activos, KB publicados, CSAT.
- Gráfico doughnut: tickets por estado.
- Gráfico líneas: creados vs resueltos (30 días).

### Dashboard Soporte (`/soporte`)

- 4 stats operativos (refresh 30s): abiertos, sin triage, alta/crítica, asignados a mí.
- 3 stats SLA: compliance %, tiempo promedio primera respuesta, CSAT.

### Reporte SLA (`/admin/sla-report`)

- Matriz departamento × prioridad con % compliance color-coded.
- Tabla de últimas 20 escalaciones.

### Exportación Excel

Seleccionar registros en Tickets o Assets y click "Exportar Excel".

---

## 8. Plantillas de ticket y Respuestas predefinidas (v1.6 con integración)

### 8.1 Plantillas de ticket

#### ¿Para qué sirven?
Formularios **pre-rellenados** que el agente aplica al **crear** un ticket repetitivo, ahorrando tiempo.

#### Caso real
El agente Juan recibe 50 veces al año "Necesito equipo para un nuevo empleado". En lugar de escribir asunto/descripción/categoría/impacto/urgencia desde cero (2 min), aplica la plantilla "Alta de equipo" en `/soporte/tickets/create` y todo se auto-llena. Solo cambia los datos del empleado específico y guarda en 15 segundos.

#### Cómo funciona (integrado en v1.6)

- Ruta CRUD: `/soporte/ticket-templates`
- Al crear ticket en `/soporte/tickets/create`:
  - Aparece un **selector "Usar plantilla"** al principio del form.
  - Lista solo las plantillas **del departamento del agente** (TI solo ve TI, etc.).
  - Al seleccionar, auto-rellena: asunto, descripción, categoría, impacto, urgencia y prioridad calculada.
  - El agente solo ajusta lo específico y guarda.

#### Campos de una plantilla

- Nombre interno (identificador para el agente)
- Asunto pre-llenado
- Descripción pre-llenada (admite placeholders tipo `[NOMBRE]`)
- Categoría (filtrada al depto del creador)
- Impacto y Urgencia por defecto
- Activa / Inactiva, Orden

#### Datos demo (v1.6)

25 plantillas sembradas (5 por depto). Ejemplos incluidos:
- TI: Solicitud de equipo nuevo, Alta de usuario Azure AD, Recuperación de correo, Instalación de software, Problema de conectividad.
- RRHH: Certificado laboral, Solicitud de vacaciones, Modificación de contrato, Incapacidad médica, Cambio de EPS/AFP.
- Compras: Compra de equipos, Cotización de software, Alta de proveedor, Compra urgente, Renovación de contrato.
- Mantenimiento: Fuga de agua, AA no enfría, Cambio de luces, Aseo profundo, Traslado de mobiliario.
- Operaciones: Falla de equipo, Actualización de PON, Parada no programada, Refuerzo de personal, Registro de producción.

### 8.2 Respuestas predefinidas (Canned responses)

#### ¿Para qué sirven?
Fragmentos de texto **reutilizables** que el agente **inserta al comentar** un ticket, ahorrando tipear lo mismo cada vez.

#### Caso real
La agente María cierra 20 tickets al día con el mismo mensaje de cierre ("Hemos resuelto tu solicitud. Si el problema vuelve..."). Crea una vez la canned response "Ticket cerrado con éxito". Después, al comentar cualquier ticket, elige esa respuesta del dropdown y se inserta automáticamente. Puede editarla antes de enviar si necesita personalizar.

#### Cómo funciona (integrado en v1.6)

- Ruta CRUD: `/soporte/canned-responses`
- Al agregar comentario en un ticket:
  - Aparece un **selector "Respuesta predefinida"** antes del campo de texto.
  - Lista solo las respuestas **del departamento del agente**.
  - Al seleccionar, el body del comentario se auto-llena con el texto de la respuesta.
  - El agente puede editar antes de guardar.

#### Campos

- Título (cómo se identifica)
- Body (el texto, admite Markdown)
- Categoría (filtrada al depto)
- Toggle "Compartida": ON = todo el equipo del depto la usa; OFF = solo el autor
- Activa / Inactiva

#### Datos demo (v1.6)

25 canned responses sembradas (5 por depto). Ejemplos: "Ticket recibido — TI", "Credenciales enviadas", "Certificado en proceso", "Cotización solicitada a proveedores", "Técnico en ruta", "Parada resuelta — reinicio".

### 8.3 Diferencia en una tabla

| | Plantilla | Canned Response |
|---|---|---|
| **Cuándo se usa** | Al **crear** un ticket | Al **comentar** un ticket |
| **Qué rellena** | Form completo (asunto, desc, categoría, impacto, urgencia) | Solo el body del comentario |
| **Ejemplo** | "Alta de empleado" | "Ticket cerrado con éxito" |
| **Scope por depto** | ✅ | ✅ |
| **Integrado en form** | ✅ (v1.6) | ✅ (v1.6) |
| **Bulk delete** | Supervisor/admin | Supervisor/admin |

---

## 9. Encuestas de Satisfacción (CSAT)

### ¿Para qué sirve?
Medir qué tan satisfechos están los usuarios con el servicio recibido, para identificar agentes destacados y áreas de mejora.

### Caso real
Al cerrar un ticket, el solicitante recibe email con link one-time: "Califica tu experiencia del 1 al 5". En el dashboard, Gerencia TI ve que el CSAT promedio del mes es 4.3/5, pero el agente X tiene 2.8/5 (requiere entrenamiento adicional).

### Funcionamiento

- Al cerrar ticket → email con token de 64 chars (expiración configurable).
- Usuario califica 1-5 estrellas + comentario opcional.
- Resultados agregados en dashboards (admin + soporte).

---

## 10. Historial de Cambios (Auditoría)

### ¿Para qué sirve?
Tener trazabilidad completa de **qué cambió, cuándo y quién lo hizo** en cada ticket, para auditorías SOX / ISO 27001 y para resolver disputas.

### Caso real
Un cliente reclama que "el ticket nunca fue atendido". El admin revisa el historial: el agente asignó el ticket a las 10:15, marcó primera respuesta a las 10:28 (dentro del SLA), comentó públicamente a las 11:00 explicando el diagnóstico, resolvió a las 14:30 y cerró a los 3 días. Trazabilidad completa.

### Implementación

- Spatie Activity Log registra cambios en `status`, `priority`, `assigned_to_id`, `department_id`, `category_id`.
- Solo campos que cambiaron + usuario + timestamp.
- Conversaciones del chatbot también se auditan.

---

## 11. SSO Azure AD (Entra ID)

### ¿Para qué sirve?
Que los empleados de Confipetrol **no tengan que recordar otra contraseña**: entran al helpdesk con sus mismas credenciales de Windows.

### Caso real
María llega a su primer día de trabajo. Va a `/auth/azure`, Microsoft la autentica, el sistema crea automáticamente su usuario en el helpdesk, lee de Azure AD que pertenece al grupo "RRHH", le asigna rol `usuario_final` y departamento "Recursos Humanos". María ya puede crear tickets sin ningún paso manual de TI.

### Flujo

1. Usuario va a `/auth/azure`.
2. Microsoft autentica.
3. Sistema crea/actualiza usuario, sincroniza departamento y rol desde grupos Azure.
4. Redirige al panel correcto según rol.

### Configuración

- Credenciales en `.env`: `AZURE_CLIENT_ID`, `AZURE_CLIENT_SECRET`, `AZURE_TENANT_ID`.
- Mapeo de grupos en `config/azure-roles.php`.

> ⚠️ **Pendiente producción:** credenciales reales de Azure (ver `docs/pendientes.md`).

---

## 12. Roles y Permisos

### ¿Para qué sirve?
Que cada usuario vea **solo lo que le corresponde** según su función, por seguridad y para no saturar la UI con datos irrelevantes.

### Caso real
El agente RRHH abre `/soporte/tickets` y ve SOLO tickets de RRHH. Si entra un ticket de TI por error, simplemente no aparece en su lista (el supervisor tendría que trasladarlo). Esto evita que personal de RRHH vea tickets confidenciales de otros departamentos (ej: incidentes de seguridad de TI).

### Roles (7)

| Rol | Panel | Descripción | Scope |
|---|---|---|---|
| super_admin | /admin + /soporte + /portal | Control total | Todo, sin filtros |
| admin | /admin + /soporte | Administración funcional | Todo, sin filtros |
| supervisor_soporte | /soporte | Supervisa grupo de su depto | Todos los tickets de su depto |
| agente_soporte | /soporte | Atiende tickets | Solo asignados + sin asignar de su depto |
| tecnico_campo | /soporte | Técnicos en sitio | Igual que agente |
| editor_kb | /soporte | Gestiona KB | KB de su depto |
| usuario_final | /portal | Crea y consulta sus tickets | Solo sus propios tickets |

### Diferencias supervisor vs agente (v1.4+)

| Capacidad | Supervisor | Agente |
|---|:---:|:---:|
| Ver todos los tickets del depto | ✅ | ❌ (solo asignados + sin asignar) |
| Crear / Editar ticket | ✅ | ✅ |
| Eliminar ticket | ✅ | ❌ |
| Trasladar ticket a otro depto | ✅ | ❌ |
| Crear agentes (`/soporte/users`) | ✅ (solo su depto) | ❌ |
| Publicar KB | ✅ | ❌ (solo Borrador) |
| Bulk delete plantillas/canned | ✅ | ❌ |
| Total permisos | 55 | 27 |

---

## 13. Módulo de Usuarios (v1.5)

### ¿Para qué sirve?
Que el super_admin pueda gestionar usuarios desde la UI (sin entrar a la BD), y que los supervisores puedan dar de alta agentes para su equipo sin depender del admin.

### Caso real
Llega un nuevo agente a RRHH. El supervisor de RRHH entra a `/soporte/users`, click "Nuevo agente", llena nombre + correo + contraseña, y listo: el sistema automáticamente le asigna rol `agente_soporte` y depto RRHH (pre-llenado desde el supervisor). El nuevo agente puede empezar a atender tickets en 30 segundos.

### Accesos

**Super_admin (`/admin/users`):**
- CRUD completo de cualquier usuario.
- Form: nombre, email (único), contraseña, rol (cualquiera), departamento.
- Departamento obligatorio para agentes/supervisores/técnicos.

**Supervisor (`/soporte/users`):**
- Solo ve y gestiona usuarios de su depto.
- Al crear, rol fijado a `agente_soporte` y depto al del supervisor (no editable).
- Redirige a la lista tras crear.

---

## 14. Scope por Departamento (v1.5)

### ¿Para qué sirve?
Aislamiento de información entre departamentos. Evita que un agente de un depto vea datos confidenciales de otro.

### Caso real
Un tema de nómina (confidencial) se crea como ticket en RRHH. Si no hubiera scope, los agentes de TI también verían el contenido (salarios, deducciones, etc.). Con el scope, el ticket es invisible para los agentes fuera de RRHH.

### Dónde aplica (filtros de Eloquent query)

- **Tickets**: agentes/supervisores solo ven los de su depto.
- **KB**: filtrado por `department_id`.
- **Plantillas**: filtrado via `category.department_id`.
- **Canned responses**: filtrado via `category.department_id`.
- **Agentes** (`/soporte/users`): supervisor solo ve agentes de su depto.

Super_admin y admin están exentos del filtro.

---

## 15. Traslado de Tickets (v1.5)

### ¿Para qué sirve?
Corregir tickets mal clasificados sin tener que cerrarlos y re-abrirlos.

### Caso real
Un usuario crea ticket "Me pagaron mal la nómina" pero selecciona categoría "TI - Software" por error. El supervisor de TI lo ve, entiende que es un tema de RRHH, y hace click en **"Trasladar a otro depto."**. Selecciona "RRHH", escribe motivo "Tema de nómina, no de TI" y confirma. El ticket desaparece de TI y aparece en RRHH. El usuario recibe un email explicándole el cambio.

### Funcionamiento

- Botón visible en vista de ticket (solo supervisor/admin, solo tickets abiertos).
- Modal pide:
  - Nuevo departamento (excluye el actual).
  - Motivo opcional (máx 500 chars).
- Backend:
  - Actualiza `department_id`.
  - Resetea `assigned_to_id` (nuevo equipo re-asigna).
  - Resetea `category_id` (requiere re-triage).
  - Envía `TicketTransferredNotification` al solicitante (mail + DB).

---

## 16. Stack Técnico

| Componente | Tecnología |
|---|---|
| Backend | Laravel 13, PHP 8.5 |
| Admin/Soporte | Filament 5.5 + 10 plugins (Shield, QuickCreate, GlobalSearch, Excel, Backup, etc.) |
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
| Tests | Pest 4 (51 tests, 123 assertions) |

---

## 17. Cuentas de prueba (6 usuarios)

Todos con password `password`.

| Email | Rol | Depto |
|---|---|---|
| admin@confipetrol.local | super_admin | — |
| supervisor@confipetrol.local | supervisor_soporte | TI |
| agente@confipetrol.local | agente_soporte | TI |
| supervisor.rrhh@confipetrol.local | supervisor_soporte | RRHH |
| agente.rrhh@confipetrol.local | agente_soporte | RRHH |
| usuario@confipetrol.local | usuario_final | Operaciones |

Los 2 pares TI/RRHH permiten demostrar el aislamiento por departamento.

---

## 18. Datos demo sembrados

Tras `php artisan migrate:fresh --seed --force`:

| Recurso | Cantidad |
|---|---|
| Roles | 7 |
| Departamentos | 5 (TI, RRHH, Compras, Mantenimiento, Operaciones) |
| Categorías | 19 |
| Config SLA | 25 |
| Flujos chatbot | 3 |
| Usuarios | 6 |
| Tickets demo | 3 |
| KB publicados (v1.6) | 35 |
| Plantillas (v1.6) | 25 |
| Canned responses (v1.6) | 25 |

---

## 19. Pendientes / roadmap

Ver [docs/pendientes.md](pendientes.md) para la lista priorizada. Destacados:

- **P1:** Envío real de correos SMTP (hoy todo va a `laravel.log`)
- **P1:** Credenciales productivas de Azure AD
- **P1:** Notificaciones en tiempo real en el portal (campanita)
- **P2:** Flujo formal de aprobación de KB con notificaciones
- **P2:** Búsqueda de KB desde el portal
- **P3:** Bot de Teams, App móvil nativa

---

## 20. Repositorio

**GitHub:** https://github.com/logo3x/helpdesk

---

*Documento actualizado 2026-04-17 — v1.6 con integración de plantillas y canned responses en los forms.*
