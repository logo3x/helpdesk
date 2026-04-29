# Pendientes — Helpdesk Confipetrol

**Última actualización:** 2026-04-25 (v1.9)

Lista priorizada de trabajo no incluido en el MVP actual. Orden de la lista = prioridad sugerida.

---

## 🔴 P0 — Pre-producción (antes del go-live)

### 0. Migrar LLM de OpenRouter a Claude API o Azure OpenAI

**Estado hoy:** todas las llamadas LLM (chatbot RAG + redacción IA de KB) van a **OpenRouter** con modelos gratuitos (Llama 3.1). Esto está bien para pruebas pero tiene implicaciones de privacidad:

- El input viaja a servidores de OpenRouter (US)
- Los modelos gratuitos pueden registrar / usar para entrenamiento el contenido
- No hay Data Processing Agreement (DPA) firmado con Confipetrol

**Qué contenidos se envían actualmente:**
- Mensajes del chatbot del usuario final (pueden incluir PII)
- Descripción del ticket escalado desde chat
- Cuerpo del KB que el agente redacta con IA (política interna, procedimientos, contactos, etc.)

**Opciones de reemplazo:**
1. **Anthropic Claude API directa** — ya soportada en `LlmService::chatAnthropic()`. Solo cambiar `LLM_PROVIDER=anthropic` y `LLM_API_KEY=sk-ant-...`. Requiere cuenta Anthropic + DPA.
2. **Azure OpenAI** — modelos GPT-4 / GPT-4o en tenant corporativo. Requiere suscripción Azure. Garantiza que los datos no salen del tenant de Confipetrol.
3. **Ollama self-hosted** — llama 3.1 en servidor interno. Cero datos a terceros. Requiere servidor con GPU.

**Acciones mínimas para producción:**
- [ ] Decidir proveedor (recomendado: Azure OpenAI por alineación con Azure AD ya usado en SSO)
- [ ] Firmar DPA correspondiente
- [ ] Rotar la API key actual de OpenRouter (expuesta durante pruebas)
- [ ] Setear `LLM_API_KEY` real + `LLM_PROVIDER` + `LLM_MODEL` en `.env` de producción
- [ ] Probar que el chatbot + la redacción IA siguen funcionando tras el switch
- [ ] Documentar en el aviso de privacidad que las consultas al chatbot son procesadas por un LLM externo

---

## 🟡 P1 — Para el próximo ciclo

### 1. Envío real de correos (SMTP corporativo)

**Estado hoy:** `MAIL_MAILER=log` — las notificaciones se guardan en `storage/logs/laravel.log` pero **no salen** a las bandejas de los usuarios.

**Qué hace falta:**
- Configurar servidor SMTP de Confipetrol (Office 365 / SendGrid / Mailgun).
- Variables en `.env`:
  ```
  MAIL_MAILER=smtp
  MAIL_HOST=smtp.office365.com
  MAIL_PORT=587
  MAIL_USERNAME=notificaciones@confipetrol.com
  MAIL_PASSWORD=****
  MAIL_ENCRYPTION=tls
  MAIL_FROM_ADDRESS=notificaciones@confipetrol.com
  MAIL_FROM_NAME="Helpdesk Confipetrol"
  ```
- Queue worker corriendo en producción (`php artisan queue:work --queue=default`).
- En **Windows Server**: registrarlo como servicio con NSSM siguiendo [docs/queue-worker-windows.md](queue-worker-windows.md). Scripts listos en `tools/queue-worker.bat` + `tools/after-deploy.bat`.
- En Linux: usar Supervisord o systemd.

**Notificaciones que esperan esto:**
- `TicketCreatedNotification` — se envía al solicitante al crear ticket.
- `TicketAssignedNotification` — al agente cuando le asignan un ticket.
- `TicketCommentedNotification` — al solicitante cuando un agente comenta.
- `TicketTransferredNotification` — al solicitante cuando el ticket cambia de depto.
- `SatisfactionSurveyNotification` — al cerrar ticket para calificar.
- Fortify: reset password, verificación email, 2FA.

**Prueba post-configuración:**
1. Crear un ticket desde el portal → verificar que el agente reciba el email.
2. Cerrar un ticket → verificar encuesta CSAT en el buzón del solicitante.
3. Trasladar ticket → verificar email con motivo al solicitante.

### 2. Integración de Azure AD SSO productiva

**Estado hoy:** código listo, falta credencial corporativa.

**Qué hace falta:**
- Registrar aplicación en Azure Portal (App Registration).
- Obtener `AZURE_CLIENT_ID`, `AZURE_CLIENT_SECRET`, `AZURE_TENANT_ID`.
- Configurar redirect URI: `https://helpdesk.confipetrol.com/auth/azure/callback`.
- Mapear grupos Azure → roles Spatie en `config/azure-roles.php`.

### 3. ~~Notificaciones en tiempo real — campanita en /portal~~ ✅ **HECHO en v1.9**

Componente `App\Livewire\Portal\NotificationsBell` agregado al header de `/portal` con polling 30s, lee shape Filament desde `data` JSON, click marca como leída + redirige según `actions[0].url` (con fallback a `ticket_id` para payload legacy), botón "Marcar todas como leídas".

---

## 🟢 P2 — Mejoras deseables

### 4. ~~Flujo de aprobación de KB articles~~ ✅ **HECHO en v1.9**

- Migración añade columnas `pending_review_at` + `pending_review_by_id` (FK users).
- Acción "Solicitar publicación" para agente → marca pending_review + notifica a supervisores del depto vía `KbArticleReviewRequestedNotification` (mail + database).
- Acción "Cancelar solicitud" para que el autor se arrepienta antes de aprobación.
- Acción "Aprobar y publicar" para supervisor → publica + notifica al autor con `KbArticlePublishedNotification`.
- Filtro "Pendientes de revisión" + columna icono en la tabla KB del panel /soporte.
- Badge en navegación: número de KB pendientes (solo visible para supervisor+).
- Pendiente menor (no bloqueante): rechazo con motivo. Por ahora se cancela la solicitud o se devuelve a draft sin notif explícita.

### 5. ~~Centro de ayuda KB en /portal~~ ✅ **HECHO en v1.9**

- `App\Livewire\Portal\KbIndex` lista paginada con buscador (LIKE en title+body) y filtros por departamento + categoría, querystring sincronizado.
- `App\Livewire\Portal\KbShow` detalle Markdown (mismo render que tickets/chatbot), 404 en draft/archived, contador de vistas (1 por sesión via session flag), feedback útil/no-útil con UPSERT atómico de contadores.
- Rutas `/portal/kb` y `/portal/kb/{slug}` + link "Centro de ayuda" en navbar.

### 6. Reporte SLA exportable a PDF

**Estado hoy:** Reporte SLA solo se muestra en pantalla.

**Qué hace falta:**
- Botón "Exportar a PDF" que genere un informe ejecutivo mensual.
- Diseño con logo Confipetrol + gráficas.
- Programar envío automático el 1° de cada mes al buzón de Gerencia TI.

### 6a. Hoja de vida del activo (inventario)

**Estado hoy:** existen las tablas `asset_history` y `asset_scans` (el modelo `AssetHistory` registra cada acción), pero **no hay UI** para verlas. El historial está disponible solo en la BD.

**Qué hace falta:**
- Tab/sección "Hoja de vida" en la vista del Asset (`/admin/assets/{id}` y `/soporte/assets/{id}`).
- Timeline cronológico que muestre:
  - **Asignaciones**: a quién se entregó, cuándo, por quién, motivo.
  - **Devoluciones / cambios de usuario**.
  - **Mantenimientos / reparaciones**: tipo, costo, proveedor externo, fechas inicio/fin.
  - **Cambios de hardware** (RAM ampliada, disco cambiado, etc.).
  - **Scans automáticos** (web-scan + agente PowerShell): qué cambió respecto al anterior.
  - **Bajas / retiros**: razón, destino (donación, scrap, transferencia).
- Action "Registrar evento manual" para que el técnico anote cosas que el scan no detecta (cambios físicos, traslados de oficina, etc.).
- Exportable a PDF para entrega del equipo al usuario o auditoría.

### 6b. Ampliar campos del inventario al modelo real Confipetrol

**Estado hoy:** la tabla `assets` tiene 25 columnas (etiqueta, hostname, serial, hardware, OS, red, asignación, status), suficiente para identificación + diagnóstico técnico, pero **falta toda la info administrativa que IT Confipetrol maneja hoy en Excel**.

**Campos faltantes según `Libro1.pdf` (inventario actual real):**

| Campo Excel actual | Tipo sugerido en BD | Notas |
|---|---|---|
| **TAG** | string (ya existe como `asset_tag`) | Mantener |
| **Serial** | string (ya existe `serial_number`) | Mantener |
| **Fabricante / Modelo** | strings (ya existen) | Mantener |
| **Código SAP** | string nuevo | Identificador contable, único cuando existe (ej: `OECC1528050500002662`) |
| **Tipo Activo** | enum / string (ya existe `type`) | Ampliar enum: laptop, desktop, all-in-one, server, printer, phone, tablet, other |
| **Estado / Condición** | string (ya existe `status`) | Renombrar valores: bueno, regular, malo, en mantenimiento, retirado |
| **Custodio** | FK `user_id` (ya existe) | Mantener |
| **Identificación** | derivado de `users.identification` | Agregar columna a `users` si no existe |
| **Cargo** | derivado de `users.position` | Agregar columna a `users` |
| **Proyecto (código)** | FK `project_id` o string libre | **Tabla nueva `projects`** con código + nombre |
| **Nombre Proyecto** | derivado de `projects.name` | — |
| **Campo** | string nuevo | Ubicación operativa (ej: PORE, SAN MARTIN, CARUPANA) |
| **Ubicación** | string nuevo | Zona dentro del campo (ej: ZONA 4) |
| **Observación** | text (ya existe `notes`) | Mantener, puede crecer |
| **Acta** | FK a `asset_handovers.id` (tabla nueva) | Última acta de entrega que aplica al equipo |
| **Línea / IMEI** | string nuevo | Solo para teléfonos celulares — usar nullable |
| **Gerencia** | string nuevo | Gerencia organizacional (ej: HSEQ, Operaciones) |
| **Correo** | derivado de `users.email` | Ya existe |
| **Último Mtto** | date nuevo `last_maintenance_at` | Fecha del último mantenimiento físico |
| **Próx Mtto** | computed o date `next_maintenance_at` | Calculado: `last_maintenance_at + maintenance_interval_days` |
| **Mtto (DIAS)** | int `maintenance_interval_days` | Frecuencia en días (típicamente 120 = trimestral) |
| **Estado Mantenimiento** | computed enum | `vigente / por vencer (≤30d) / vencido` |
| **Responsable** | FK `maintenance_responsible_id` → users | Técnico encargado de mantenimientos |

**Adicionales que conviene agregar (no están en el Excel pero son estándar):**
- **Compra**: fecha de compra, costo, moneda, número de orden de compra, proveedor.
- **Garantía**: fecha de inicio, fecha de vencimiento, tipo (extendida/estándar), contacto.
- **Adjuntos**: factura, certificado de garantía, foto del equipo (Spatie MediaLibrary).
- **Periféricos asociados**: monitor adicional, teclado, mouse, dock — relación 1:N a `asset_peripherals`.

**Implicación técnica:**
- Migración con ~12 columnas nuevas en `assets` + tablas nuevas `projects`, `asset_handovers`, `asset_peripherals`.
- Columnas nuevas en `users`: `identification`, `position`, `phone` (si no existen).
- Update del `AssetForm` agregando secciones "Asignación administrativa" (proyecto, campo, ubicación, gerencia) y "Mantenimiento" (último, frecuencia, próximo, responsable).
- Widget en dashboard /admin: "Equipos con mantenimiento vencido" (cruzando `next_maintenance_at < now()` con `status = active`).
- Importador desde Excel: comando Artisan `inventory:import-from-xlsx` para cargar el inventario actual (`Libro1.pdf` se origina en un xlsx).

### 6e. Acta de entrega de activo (PDF)

**Formato oficial Confipetrol:** `IT-ADM1-F-5 versión 3 (2024-07-24)` — ver [11829_JOSE RONALDO BARRIO TELLEZ.pdf](Libro1.pdf) como referencia.

**Estado hoy:** las actas se llenan a mano (Word/PDF editable) y se almacenan dispersas. No hay link entre el acta y el activo en BD, ni quién hizo la entrega, ni cuándo, ni quién recibe.

**Qué hace falta:**

1. **Tabla `asset_handovers`** con:
   - `asset_id` (FK)
   - `acta_number` (ej: 1432, secuencial autogenerado)
   - `delivered_by_user_id` (entrega — quien firma de IT)
   - `received_by_user_id` (custodio — quien recibe el equipo)
   - `delivered_at` (fecha)
   - `condition_at_delivery` (bueno / regular)
   - `project_id`, `field` (campo), `location` (ubicación)
   - `observations` (texto libre — ej. "Acta #: 1432 --- CON CARGADOR")
   - `pdf_path` (ruta al PDF generado, si se quiere persistir)
   - `signed_pdf_path` (ruta al PDF ya firmado/escaneado, si se sube después)

2. **Acción "📄 Generar acta de entrega"** en el detalle del activo (`/admin/assets/{id}` y `/soporte/assets/{id}`):
   - Modal pidiendo: usuario receptor (Select buscable, default = `user_id` actual del activo), proyecto, campo, ubicación, condición de entrega, observaciones extra.
   - Al confirmar: crea fila en `asset_handovers` y genera el PDF replicando el formato oficial (logo Confipetrol, código IT-ADM1-F-5 v3, todos los datos del equipo y del receptor, los párrafos legales tal cual el formato).
   - Botón en el modal "Descargar PDF" tras generar.
   - Opcionalmente: enviar el PDF al correo del receptor automáticamente para que lo firme y lo devuelva.

3. **Subir acta firmada** (acción complementaria): el técnico escanea el PDF firmado y lo sube → se guarda en `signed_pdf_path` con Spatie MediaLibrary.

4. **Historial de actas en la "Hoja de vida"** (#6a): la sección timeline lista todas las actas del activo en orden cronológico — muestra entrega inicial, devolución, re-asignación, etc. Cada fila tiene link al PDF.

**Stack sugerido:** `barryvdh/laravel-dompdf` o `spatie/browsershot` (mejor renderizado de Tailwind/CSS para coincidir con el formato oficial). Plantilla Blade en `resources/views/pdfs/asset-handover.blade.php` con el layout exacto del formato.

**Consideración legal:** el texto de los párrafos de responsabilidad/devolución debe respetarse al pie de la letra del formato oficial — copiar directo del PDF de referencia. Si el formato oficial de Confipetrol cambia, IT actualiza la plantilla Blade y la columna `acta_template_version` permite saber qué versión usó cada acta histórica.

### 6c. Verificación de calidad de respuestas IA contra los KB

**Estado hoy:** el chatbot usa `RagService` que busca en KB articles con `status='published'` y los pasa como contexto al LLM. **No hay forma de medir** si las respuestas son fieles al KB o si "alucina" (inventa info que no está en los artículos).

**Qué hace falta:**
- **Tab "Conversaciones del chatbot"** en /admin → revisar historial de chat sessions reales.
- **Sistema de feedback** del usuario sobre cada respuesta del bot (👍/👎 + motivo opcional). Ya existe la idea en `kb_article_feedback`; replicar el patrón para `chat_messages`.
- **Métrica**: % de respuestas marcadas como útiles por mes/depto.
- **Test automatizado periódico**: un set de preguntas de referencia con su respuesta esperada (cargado desde `tests/Fixtures/chatbot-qa.yaml`). Job programado que ejecute cada pregunta, compare la respuesta con la esperada usando similitud semántica (embeddings) y reporte regresiones.
- **Trazabilidad**: cada respuesta del bot debe citar qué artículos KB consultó (ya hay info en `chat_messages.context_kb_ids` posiblemente). Mostrar las fuentes al usuario para que pueda verificar.
- **Acción supervisor**: marcar una respuesta como "incorrecta" → genera automáticamente un ticket interno para revisar el KB que la causó.

### 6d. Asistentes IA para Plantillas de ticket y Respuestas predefinidas

**Estado hoy:** existe el botón "✨ Redactar con IA" en KB articles ([CreateKbArticle](app/Filament/Soporte/Resources/KbArticles/Pages/CreateKbArticle.php) → `LlmService::draftKbArticle()`). El mismo patrón sirve para acelerar la creación de:

- **TicketTemplates**: el agente describe el caso ("plantilla para solicitud de equipo nuevo, debe pedir nombre, cargo, periféricos, fecha de inicio, software requerido") y el LLM genera asunto + descripción estructurada con la checklist.
- **CannedResponses**: el agente describe la respuesta ("respuesta para cuando un usuario pide reset de contraseña en pleno fin de semana") y el LLM la redacta con el tono apropiado (formal/amigable/técnico).

**Qué hace falta:**
- Agregar `LlmService::draftTicketTemplate()` y `LlmService::draftCannedResponse()` con prompts específicos.
- Botón "✨ Redactar con IA" en `CreateTicketTemplate` y `CreateCannedResponse` (mismo modal con textarea + tono).
- Reutilizar la infraestructura de feature flag `services.llm.kb_drafting_enabled` (renombrar a `services.llm.drafting_enabled` ya que cubre 3 casos).
- Tests del happy path en cada caso.

### 7. ~~Dashboard para supervisores~~ ✅ **HECHO en v1.9**

`TicketStatsWidget` ahora respeta el rol del usuario:
- super_admin/admin → totales globales (descripción "Todo el sistema").
- supervisor_soporte → solo de su departamento + 2 stats extra: "Mi equipo" (agentes/técnicos del depto) y "KB por aprobar" (drafts con pending_review_at).
- agente/técnico → solo su cola (asignados a él o sin asignar dentro de su depto).

Pendiente menor (no bloqueante): ranking de agentes por productividad (tickets resueltos / CSAT / tiempo respuesta) — se hará cuando haya datos reales en producción para definir el corte.

---

## 🔵 P3 — Nice-to-have

### 8. Plantilla de tickets recurrentes (cron)

Permitir configurar una plantilla que se dispara automáticamente. Ejemplo: "Mantenimiento preventivo de impresoras" cada 3 meses crea un ticket.

### 9. Bot de Teams

Integrar el chatbot con Microsoft Teams para que los usuarios lo usen sin abrir el portal.

### 10. App móvil nativa (React Native / Flutter)

Portal responsive funciona bien, pero una app nativa daría mejor experiencia para usuarios finales en campo.

### 11. Integración Jira / ServiceNow

Puente bidireccional para sincronizar tickets con herramientas externas de clientes corporativos.

### 12. IA agent-to-agent

El chatbot sugiere al agente humano soluciones basadas en tickets históricos similares resueltos.

### 13. Optimizar despliegue del agente PowerShell de inventario

**Estado hoy:** el script existe ([public/downloads/inventory-agent.ps1](public/downloads/inventory-agent.ps1)), el endpoint `/api/inventory/agent-scan` funciona y hay UI para generar tokens. El obstáculo es **operativo**: requiere acceso al ticker de la red corporativa (cada PC debe poder llamar a la URL del helpdesk), generar un token Sanctum por equipo o uno común, y desplegar el script + tarea programada en cada PC. Son demasiados pasos manuales para desplegar a >100 equipos.

**Qué hace falta para optimizarlo (cuando se retome):**
- Modo "auto-instalador": un único `.exe` o `.msi` firmado que IT despliegue por GPO y deje todo configurado (script + token + task scheduler).
- O bien: un solo token compartido por la flota con rotación periódica desde el panel.
- Documentar la URL pública del helpdesk para que las PCs puedan alcanzarla desde la red corporativa (¿debe estar dentro o fuera de la VPN?).
- Añadir telemetría: que el panel marque qué equipos reportaron en las últimas 24/72/168h (el widget "Equipos sin scan reciente" ya cubre parte).
- Considerar reemplazar el agente PowerShell por una integración con la herramienta de gestión existente (ManageEngine, Lansweeper, etc.) si ya tienen una.

Por ahora solo se está usando el **web-scan** (ligero, automático al abrir el portal). Cubre el caso "saber qué usuarios usan qué OS/IP" pero no detalles de hardware ni software instalado.

---

## ✅ Ya implementado (no son pendientes)

Marcado para saber qué ya funciona:

- [x] CRUD de tickets con ciclo de vida completo
- [x] Motor SLA con alertas 70/90/100%
- [x] Portal usuario con Livewire
- [x] Chatbot con RAG + LLM
- [x] Inventario PCs (web scan + agente PowerShell)
- [x] RBAC Spatie + Filament Shield
- [x] 3 paneles Filament (/admin, /soporte, /portal)
- [x] **v1.5:** Módulo Usuarios
- [x] **v1.5:** Scope por departamento (tickets, KB, plantillas, canned)
- [x] **v1.5:** Traslado de tickets entre departamentos
- [x] **v1.5:** KB con categoría = departamento y flujo draft→publicado
- [x] **v1.5:** Plantillas y canned responses con categoría filtrada por depto
- [x] **v1.6:** Integración: selector de plantilla en crear-ticket
- [x] **v1.6:** Integración: selector de canned response al comentar
- [x] **v1.6:** 75 registros demo sembrados (25 KB + 25 plantillas + 25 canned)
- [x] **v1.7:** Asistente IA para redactar KB en lenguaje natural (OpenRouter / Llama)
- [x] **v1.7:** Auto-comentario público al asignar ticket (UX para solicitante)
- [x] **v1.7:** Auto-asignación al marcar primera respuesta
- [x] **v1.7:** Notificación a supervisores del depto destino al trasladar ticket
- [x] **v1.8:** Acción "Recalibrar prioridad" con audit log (motivo en `activity_log.properties`)
- [x] **v1.8:** Edit de ticket reducido (asunto/descripción/categoría) — el resto vía acciones del detalle
- [x] **v1.8:** Database notifications (campanita) en /admin y /soporte con shape Filament + URL por rol
- [x] **v1.8:** Eliminada columna `visibility` de KB — solo `status` controla quién ve qué
- [x] **v1.9:** Centro de ayuda KB en /portal (lista, detalle, feedback, contador de vistas)
- [x] **v1.9:** Campanita de notificaciones en /portal (Livewire, polling 30s)
- [x] **v1.9:** Flujo de aprobación KB (solicitar publicación → notif supervisor → aprobar y publicar)
- [x] **v1.9:** Stats del panel /soporte scoped por rol (admin global, supervisor depto, agente cola)
- [x] **v1.9:** Login unificado — eliminados /admin/login y /soporte/login, todo pasa por /login (Fortify) con branding Confipetrol
- [x] **v1.9:** Dashboard de bienvenida en /portal (saludo, stats personales, accesos rápidos, últimos tickets, KB destacados)
- [x] **v1.9:** Vista del ticket en /portal rediseñada (thread tipo email con avatares y barra lateral coloreada)
- [x] **v1.9:** Vista del ticket en /soporte con infolist organizado (resumen → descripción → adjuntos → SLA → clasificación)
- [x] **v1.9:** Form de creación de ticket por preguntas naturales (¿Plantilla? ¿Cuál es el problema? ¿Para quién? ¿Qué tan crítico?)
- [x] **v1.9:** Acción "Tomar este ticket" con respuesta predefinida + textarea editable (no más saludo genérico fijo)
- [x] **v1.9:** Reporte SLA en /soporte (antes solo /admin), scoped por depto para supervisores
- [x] **v1.9:** Categorías administrables por supervisor (solo de su depto)
- [x] **v1.9:** Inventario configurable por departamento (toggle `can_access_inventory` en Departamentos)
- [x] **v1.9:** AssetPolicy reescrita — derivada del rol + flag de depto, no de Shield permissions
- [x] **v1.9:** AssetForm rediseñado con 6 secciones colapsables y selects con buscador
- [x] **v1.9:** Generación de tokens del agente desde la UI (sin tinker)
- [x] **v1.9:** Despliegue del agente PowerShell con un solo comando (`iex (irm /agent/install?token=...)`)
- [x] **v1.9:** Widget "Equipos sin scan reciente (>30 días)" en dashboard /admin
- [x] **v1.9:** Exportación Excel/CSV del inventario con filtros aplicados
- [x] **v1.9:** Stats del dashboard /admin clickeables con filtros pre-aplicados
- [x] **v1.9:** Mensajes de validación 100% en español (lang/es/{validation,auth,passwords,pagination}.php)
- [x] **v1.9:** Sanctum statefulApi() activado para que web-scan funcione con cookies de sesión

---

*Este documento se actualiza en cada release. Prioridades revisables.*
