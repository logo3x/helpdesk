# Product Requirements Document (PRD)
## Helpdesk Confipetrol

**Versión:** 1.5
**Fecha:** 17 de abril de 2026
**Responsable de producto:** Luis Oviedo — luis.oviedo@confipetrol.com
**Repositorio:** https://github.com/logo3x/helpdesk
**Estado:** En desarrollo (MVP funcional, fase de pruebas con cliente)

### Changelog

| Versión | Fecha | Cambios principales |
|---|---|---|
| 1.5 | 2026-04-17 | **Módulo Usuarios** (super_admin en `/admin/users` + supervisor en `/soporte/users`); **scope por departamento** en tickets/KB/plantillas/canned; **traslado de tickets** entre deptos con notificación al solicitante; KB con categoría = departamento y flujo de aprobación (agente crea borradores, supervisor publica); plantillas y canned responses con categoría filtrada por depto; ChatSessions oculto; 6 usuarios demo (2 deptos). |
| 1.4 | 2026-04-16 | Diferenciación real de permisos supervisor (51) vs agente (23). |
| 1.3 | 2026-04-16 | Plan de testing actualizado para demo con cliente. |
| 1.2 | 2026-04-16 | Fixes P0 tras TestSprite (3 formularios Filament vacíos implementados). |
| 1.0 | 2026-04-16 | Primera versión del PRD. |

---

## 1. Resumen ejecutivo

### 1.1 Problema
Confipetrol opera actualmente con **GLPI** como sistema de mesa de ayuda. GLPI presenta limitaciones de usabilidad para los usuarios finales, dificultad para integrarlo con Azure AD (SSO corporativo), ausencia de automatización inteligente (chatbot, RAG) y reportes SLA poco visuales. La experiencia es fragmentada: los usuarios no saben a qué área escalar un problema, los agentes carecen de visibilidad consolidada y la dirección no tiene métricas confiables de cumplimiento.

### 1.2 Solución
Una **plataforma interna de mesa de ayuda unificada** que centraliza todas las solicitudes de soporte (TI, RRHH, Compras, Mantenimiento, Operaciones) en una sola herramienta, con:
- Tres paneles especializados por audiencia (Admin, Soporte, Portal usuario).
- Chatbot híbrido con RAG + LLM que resuelve consultas frecuentes sin intervención humana.
- Motor SLA automático con alertas escalonadas (70/90/100%).
- Inventario de PCs con agente PowerShell + web scan.
- SSO Azure AD y auditoría completa.

### 1.3 Objetivo de negocio
- **Reducir en 30%** los tickets repetitivos en los primeros 6 meses mediante la Base de Conocimiento y el chatbot.
- **Elevar el CSAT** a ≥ 4.2 / 5 en primer trimestre operando en producción.
- **Cumplir ≥ 90%** los SLA críticos del departamento de TI.
- **Trazabilidad 100%** de hardware, software y cambios en tickets (auditoría SOX / ISO 27001).

---

## 2. Audiencias y perfiles

| Perfil | Panel | Qué espera obtener |
|---|---|---|
| **Usuario final** (empleado Confipetrol) | `/portal` | Crear tickets en < 1 minuto, resolver dudas básicas sin esperar agente, ver estado de sus solicitudes. |
| **Agente de soporte** | `/soporte` | Bandeja de tickets asignados, SLA visible, comentarios internos, canned responses. |
| **Supervisor de soporte** | `/soporte` | Vista de todo el equipo, reasignación, reportes operativos en vivo. |
| **Técnico de campo** | `/soporte` | Tickets con componente físico, vista mobile-friendly. |
| **Editor KB** | `/soporte` | Crear y versionar artículos de conocimiento. |
| **Admin TI** | `/admin` | Configuración global, SLA matrix, dashboards ejecutivos, gestión de roles. |
| **Super admin** | `/admin` + `/soporte` | Control total, auditoría, backups, integración Azure. |
| **Agente PowerShell** (no humano) | `/api` | Endpoint autenticado con Sanctum para reportar inventario. |

---

## 3. Alcance funcional (MVP)

### 3.1 Módulos incluidos (must-have)
1. **Sistema de tickets** — ciclo de vida completo, matriz ITIL, adjuntos, **traslado entre departamentos**.
2. **Motor SLA** — monitoreo automático, alertas, pausa en "pendiente cliente".
3. **Portal de usuario final** — crear, consultar, comentar tickets, chatbot.
4. **Chatbot híbrido** — flujos guiados + RAG + LLM + escalación a ticket.
5. **Base de conocimiento** — categoría = departamento, flujo de aprobación (agente crea borrador, supervisor publica), versionado, feedback útil/no útil.
6. **Inventario PCs** — activos, componentes, software, historial.
7. **SSO Azure AD** — login único corporativo con sync de roles.
8. **RBAC con scope por departamento** — 7 roles, 77+ permisos por recurso, visibilidad de datos limitada al departamento del usuario.
9. **Módulo de Usuarios (v1.5)** — super_admin crea cualquier usuario en `/admin/users`; supervisor crea agentes de su depto en `/soporte/users`.
10. **Plantillas de ticket y respuestas predefinidas** — categorías filtradas por depto, bulk-delete solo para supervisor+admin.
11. **Reportes y dashboards** — admin, soporte, reporte SLA.
12. **Auditoría** — activity log de cambios en tickets + conversaciones chat.

### 3.2 Fuera de alcance (v1)
- App móvil nativa (el portal es responsive).
- Integración WhatsApp / Teams bot (planeado v2).
- Multi-tenant (Confipetrol es instancia única).
- Facturación a clientes externos.
- Firma digital de tickets.

---

## 4. Requisitos funcionales detallados

### 4.1 Tickets
**Creación (usuario final / `/portal/tickets/create`):**
- Asunto (5–100 caracteres, requerido).
- Descripción libre (markdown soportado).
- Categoría (dependiente de departamento).
- Impacto: Bajo / Medio / Alto.
- Urgencia: Baja / Media / Alta.
- Adjuntos: hasta 5 archivos, 10 MB c/u (imágenes, PDF, Office, ZIP, RAR).
- **Prioridad calculada automáticamente** con matriz ITIL Impacto × Urgencia.

**Numeración:**
- Formato `TK-YYYY-NNNNN` (atómico, sin colisiones, reinicia cada año).

**Ciclo de vida:**
```
Nuevo → Asignado → En progreso → Resuelto → Cerrado
                                     ↑          |
                               Reabierto ←──────┘
              Pendiente cliente (pausa el reloj SLA)
```

**Comentarios:**
- Públicos (visibles al solicitante) o **internos** (solo agentes).
- El primer comentario público del agente marca automáticamente "primera respuesta" para SLA.
- Notificaciones bidireccionales vía email.

**Reglas de negocio:**
- Un ticket cerrado no acepta comentarios.
- Solo el solicitante o agente asignado pueden reabrir dentro de 30 días.
- Cambios a `status`, `priority`, `assigned_to` se auditan automáticamente.

### 4.2 SLA
**Tiempos por prioridad (horario laboral Bogotá L-V 08:00–18:00):**

| Prioridad | Primera respuesta | Resolución |
|---|---|---|
| Crítica | 30 min | 4 horas |
| Alta | 1 hora | 8 horas |
| Media | 2 horas | 20 horas |
| Baja | 4 horas | 40 horas |
| Planificada | 8 horas | 100 horas |

**Alertas:** job cada 5 min revisa tickets abiertos y genera notificaciones a 70%, 90% y 100% (breach).

**Auto-cierre:** job diario 6am cierra tickets que llevan ≥ 7 días en "Resuelto".

### 4.3 Chatbot
**Pipeline de decisión (en orden):**
1. ¿El usuario quiere escalar? → Sugerir crear ticket.
2. ¿Hay flujo activo? → Continuar paso a paso.
3. Clasificar intent por keywords → Si match ≥ 30%, iniciar flujo.
4. Buscar en KB (RAG vectorial con fallback keyword) → Si hay artículos, generar respuesta con LLM.
5. LLM sin contexto → Respuesta general.
6. Fallback → "¿Quieres crear un ticket?"

**Flujos predefinidos:**
- Reset de contraseña.
- Configurar VPN.
- Problemas de impresoras.
- (Extensible desde `/admin`.)

**Escalación a ticket:**
- Comando `escalar: [resumen]` → crea ticket con **transcripción completa en markdown**, emojis por rol (👤 usuario / 🤖 asistente), timestamps, metadata.

**Persistencia:**
- `ChatSession` por usuario, resumible si hay actividad ≤ 30 min.
- `ChatMessage` almacena todos los turnos para auditoría.
- LLM usa ventana de últimos 10 mensajes como contexto.

**Auditoría (/admin/chat-sessions):**
- Listado filtrable por estado (activa/escalada/cerrada) y canal.
- Vista de transcripción completa con markdown renderizado.
- Link al ticket escalado si aplica.

### 4.4 Base de Conocimiento
- Categorías jerárquicas (padre/hijo).
- Artículos con título, cuerpo (markdown), categoría, autor, tags.
- Estados: borrador → publicado → obsoleto.
- Visibilidad: público / interno / por grupo.
- **Versionado**: cada edición crea snapshot con nº de versión, editor, cambios.
- Feedback "útil / no útil" (1 voto por usuario) + comentario opcional.
- Embeddings generados automáticamente al publicar (job en cola).

### 4.5 Inventario
**Doble captura:**
- **Web Scan (JS)**: OS, CPU cores, RAM estimada, GPU (WebGL), resolución — 1 vez/día al visitar portal.
- **Agente PowerShell**: hardware completo, BIOS, serial, discos, software instalado — auth Sanctum Bearer.

**Datos:**
- Asset: hostname, serial, fabricante, modelo, OS, CPU, RAM, disco, GPU, IP, MAC, estado.
- Software por activo (sincroniza lista completa en cada scan).
- Componentes con specs JSON flexibles.
- Historial de cambios.
- Raw scans (payload completo para auditoría).

### 4.5.1 Módulo de Usuarios (v1.5)

**Acceso super_admin (`/admin/users`):**
- CRUD completo de usuarios.
- Form: nombre, email (único), contraseña (mín 8), rol (select único), departamento.
- Departamento obligatorio para roles supervisor/agente/técnico.
- Contraseña vacía en edit = mantener la actual.
- Filtros por rol y por departamento.

**Acceso supervisor (`/soporte/users`):**
- Solo ve y gestiona usuarios de su propio departamento.
- Al crear, el rol se asigna automáticamente a `agente_soporte`.
- El departamento se pre-llena con el del supervisor y **no es editable**.
- Redirige a la lista tras crear/editar.

### 4.5.2 Scope por departamento (v1.5)

El RBAC se extiende con **filtros por departamento** aplicados a nivel de Eloquent query en los Resources Filament:

- **Tickets** (`TicketResource::getEloquentQuery`): agentes/supervisores solo ven tickets de su depto; agentes adicionalmente se limitan a sus asignados + sin asignar.
- **KB articles** (`KbArticleResource::getEloquentQuery`): filtrado por `department_id` directo.
- **Templates** (`TicketTemplateResource::getEloquentQuery`): filtrado via `category.department_id`.
- **Canned responses** (`CannedResponseResource::getEloquentQuery`): filtrado via `category.department_id`, permite también canned sin categoría.

Super_admin y admin están exentos de estos filtros.

### 4.5.3 Traslado de tickets entre departamentos (v1.5)

Cuando un ticket queda mal clasificado, el supervisor (o admin) puede trasladarlo:

- Acción **"Trasladar a otro depto."** visible en la vista del ticket.
- Solo disponible para `super_admin`, `admin`, `supervisor_soporte` y tickets abiertos.
- Modal pide departamento destino y motivo opcional (máx 500 chars).
- Backend:
  - Actualiza `department_id`.
  - Resetea `assigned_to_id` (nuevo depto re-asigna).
  - Resetea `category_id` (requiere re-triage).
  - Envía `TicketTransferredNotification` al solicitante (mail + DB).
- Ticket desaparece de la lista del depto origen, aparece en el destino.

### 4.6 SSO Azure AD
- Flujo OAuth2 con `laravel/socialite` + `socialiteproviders/microsoft`.
- Crea/actualiza usuario, sincroniza departamento y rol desde grupos Azure.
- Redirige al panel correcto según rol.
- Credenciales vía `.env` (AZURE_CLIENT_ID, AZURE_CLIENT_SECRET, AZURE_TENANT_ID).
- Mapeo de grupos en `config/azure-roles.php`.

### 4.7 Autenticación local
- Fortify (sin auto-registro — usuarios creados por admin o SSO).
- Reset password por email.
- 2FA opcional (TOTP con códigos de recuperación).
- Confirmación de contraseña para áreas seguras.

### 4.8 Reportes
- **Dashboard Admin**: 5 stats (tickets abiertos con breach, total histórico, usuarios, KB publicados, CSAT) + gráficos.
- **Dashboard Soporte**: 4 stats operativos con refresh 30s + 3 stats SLA.
- **Reporte SLA**: matriz departamento × prioridad con % compliance color-coded + tabla de escalaciones recientes.
- **Export Excel**: tickets y assets vía pxlrbt/filament-excel.

### 4.9 Encuestas CSAT
- Al cerrar ticket se envía email con link one-time (token 64 chars, expiración configurable).
- Calificación 1–5 estrellas + comentario opcional.
- Resultados agregados en dashboards.

---

## 5. Requisitos no funcionales

### 5.1 Rendimiento
- Tiempo de carga inicial del portal < 2s en red interna.
- Creación de ticket < 500ms.
- Respuesta de chatbot ≤ 5s (incluye RAG + LLM).
- Dashboards con caché de 60s para stats costosas.

### 5.2 Disponibilidad
- Objetivo 99.5% (horario laboral).
- Backup diario automático (Spatie Backup → S3 o almacenamiento local).
- Retención de backups: 30 días.

### 5.3 Seguridad
- CSRF en todos los formularios (Laravel default).
- Mass-assignment protegido vía `$fillable` en modelos.
- Validación estricta en Form Requests / Livewire rules.
- Rate limiting en login (Fortify) y API (Sanctum).
- Passwords con `Password::defaults()` en producción (mínimo 12, mixto, no comprometida).
- Roles/permisos evaluados vía policies (Filament Shield).
- Auditoría con Spatie Activity Log.

### 5.4 Mantenibilidad
- Código formateado con Laravel Pint (estándar Laravel).
- Tests con Pest 4: actualmente 53 tests / 128 assertions.
- Commits con Conventional Commits en español.
- ADRs / docs en `/docs`.

### 5.5 Accesibilidad y UX
- Interfaz 100% en **español**.
- Labels con tooltips `?` informativos en formularios complejos.
- Modo claro y oscuro con logos institucionales Confipetrol adaptativos.
- Responsive (portal y paneles Filament).

### 5.6 Internacionalización
- Idioma único: español (Colombia).
- Zona horaria: `America/Bogota`.
- Formato fechas: `dd/MM/yyyy HH:mm`.

---

## 6. Arquitectura y stack

| Componente | Tecnología |
|---|---|
| Backend | Laravel 13, PHP 8.5 |
| Admin/Soporte | Filament 5.5 + 9 plugins (Shield, QuickCreate, GlobalSearch, Excel, Backup, etc.) |
| Portal | Livewire 4 + Flux 2 |
| CSS | Tailwind 4 |
| Auth | Fortify (2FA) + Socialite (Azure SSO) |
| API | Sanctum |
| RBAC | Spatie Permission + Filament Shield |
| Adjuntos | Spatie Media Library |
| Auditoría | Spatie Activity Log |
| Backup | Spatie Backup |
| Exportación | pxlrbt/filament-excel |
| LLM | OpenRouter (default free tier) / Anthropic Claude (configurable) |
| Embeddings | OpenAI/Voyage (configurable), con fallback keyword |
| BD | MySQL 8 (InnoDB, utf8mb4) |
| Tests | Pest 4 |
| CI/CD | GitHub Actions (build + pint + pest) |

### 6.1 Estructura de paneles
- `/admin` (Filament, color Amber) → administración TI.
- `/soporte` (Filament, color Sky) → agentes y técnicos.
- `/portal` (Livewire + Flux) → usuarios finales.
- `/api/v1` (Sanctum) → integración PowerShell agent.

### 6.2 Organización de código
- DDD ligero con recursos Filament agrupados por contexto.
- Servicios de dominio en `app/Services` (TicketService, ChatbotService, SlaService, RagService, LlmService).
- Policies por modelo (Spatie + Shield auto-genera).
- Livewire pages SFC para el portal en `resources/views/pages`.

---

## 7. Flujos de usuario críticos

### 7.1 Usuario final crea ticket
1. Accede a `/portal` (auto-login via SSO o login manual).
2. Click "Crear ticket" o va al chatbot.
3. Llena el formulario (asunto, descripción, categoría, impacto, urgencia).
4. Sube adjuntos si aplica.
5. Envía → recibe nº de ticket + email de confirmación.
6. Ve el ticket en "Mis tickets" con estado en vivo.

### 7.2 Agente atiende ticket
1. Accede a `/soporte` → ve bandeja filtrada por "asignados a mí".
2. Abre ticket → lee descripción, adjuntos, historial.
3. Agrega comentario público → se marca primera respuesta SLA.
4. Cambia estado a "En progreso" → asigna a sí mismo si no lo estaba.
5. Resuelve → `resolved_at` se marca → email al solicitante con botón "Confirmar cierre".
6. Cierra → email con encuesta CSAT.

### 7.3 Chatbot resuelve sin ticket
1. Usuario entra a `/portal/chatbot`.
2. Pregunta "¿Cómo cambio mi contraseña?".
3. Bot detecta intent "reset password" → inicia flujo con 3 pasos.
4. Usuario completa → bot marca flujo como exitoso.
5. Si falla → usuario escribe `escalar: No puedo cambiar la contraseña` → se crea ticket con transcripción.

### 7.4 Admin revisa SLA
1. Accede a `/admin/sla-report`.
2. Ve matriz departamento × prioridad con % compliance.
3. Identifica grupo con bajo compliance → click → ve tickets breach.
4. Reasigna o escala a supervisor.

---

## 8. Criterios de aceptación

### 8.1 Tickets
- [x] Creación desde portal con todos los campos obligatorios validados.
- [x] Numeración `TK-2026-NNNNN` sin colisiones en concurrencia.
- [x] Matriz ITIL aplicada correctamente en todas las 9 combinaciones.
- [x] Cambios auditados (Spatie Activity Log).
- [x] Adjuntos vía Spatie Media (tipos y tamaño validados).

### 8.2 Chatbot
- [x] Responde en < 5s con formato markdown renderizado.
- [x] Escalación genera ticket con transcripción completa.
- [x] Conversaciones persistidas y consultables desde `/admin/chat-sessions`.
- [x] Fallback si no hay API key LLM.

### 8.3 SSO
- [ ] Login con usuario corporativo Azure funciona end-to-end.
- [ ] Rol se asigna desde grupos Azure mapeados.
- [ ] Usuario existente se actualiza en lugar de duplicarse.

### 8.4 UX
- [x] Todas las pantallas en español (login, settings, portal, paneles).
- [x] Logo Confipetrol institucional se muestra en modo claro (azul) y oscuro (blanco).
- [x] Tooltips informativos en labels de formularios complejos.

---

## 9. Métricas de éxito

| KPI | Meta | Medición |
|---|---|---|
| % resolución por chatbot sin escalar | ≥ 30% | Total sesiones con verdict "resuelto" / total sesiones |
| SLA compliance global | ≥ 90% | Tickets resueltos en tiempo / total cerrados |
| Tiempo promedio primera respuesta | ≤ 2h | `first_responded_at - created_at` promedio ponderado |
| CSAT | ≥ 4.2 / 5 | Promedio de encuestas del último trimestre |
| Uso del portal | 100% empleados activos | Usuarios únicos mensuales |
| Tickets creados desde chatbot | ≥ 20% | `ChatSession.escalated_ticket_id IS NOT NULL` / total tickets |
| Disponibilidad | ≥ 99.5% | Uptime monitor |

---

## 10. Roadmap

### ✅ Fase 1 — MVP (completo)
- Módulos 1–10 listados en 3.1.
- Autenticación local + 2FA.
- Portal, paneles y API operativos.

### 🚧 Fase 2 — Integración corporativa (en curso)
- SSO Azure AD end-to-end validado.
- Despliegue productivo con backups automáticos.
- Piloto con 20 usuarios de TI.

### 📋 Fase 3 — Escalamiento (próximos 3 meses)
- Rollout a todas las áreas de Confipetrol (~500 usuarios).
- Integración con Teams (bot nativo).
- Notificaciones push en el portal (Laravel Reverb / Echo).
- Plantillas de workflows aprobados por RRHH.

### 🔭 Fase 4 — Expansión (6+ meses)
- App móvil nativa con React Native o Flutter.
- Integraciones: Google Workspace, Jira, ServiceNow (bridging).
- IA agent-to-agent (el chatbot sugiere soluciones a los agentes).
- Analytics avanzados con Metabase / Superset.

---

## 11. Riesgos y mitigaciones

| Riesgo | Impacto | Probabilidad | Mitigación |
|---|---|---|---|
| API de OpenRouter rate-limit | Alto | Media | Retry con backoff + fallback a keyword search en RAG. |
| Azure AD cambia esquema de grupos | Medio | Baja | Mapeo en config desacoplado del código; logs detallados. |
| Migración desde GLPI sin datos completos | Alto | Alta | Importer manual de usuarios y KB; tickets históricos solo referenciables. |
| Resistencia al cambio de usuarios | Alto | Media | Onboarding con videos cortos + chatbot guía + campeones por área. |
| Crecimiento de BD por adjuntos | Medio | Alta | S3 para media en producción + política de retención 2 años. |

---

## 12. Supuestos y dependencias

**Supuestos:**
- Confipetrol mantiene MySQL 8+ en infraestructura actual.
- Los usuarios ya tienen cuenta en Azure AD corporativo.
- Disponibilidad de API key LLM (OpenRouter free o Claude pagado).

**Dependencias externas:**
- Microsoft Azure AD / Entra ID.
- OpenRouter o Anthropic.
- SMTP corporativo para notificaciones.
- WAMP / XAMPP en desarrollo, LAMP o Laravel Cloud en producción.

---

## 13. Glosario

| Término | Definición |
|---|---|
| **SLA** | Service Level Agreement — tiempos máximos contractuales para primera respuesta y resolución. |
| **Breach** | Vencimiento del SLA (100% del tiempo consumido). |
| **CSAT** | Customer Satisfaction Score — calificación 1–5 post-cierre. |
| **Matriz ITIL** | Tabla impacto × urgencia que determina la prioridad. |
| **RAG** | Retrieval Augmented Generation — buscar en KB y usar resultados como contexto del LLM. |
| **Flow** | Conversación guiada paso a paso del chatbot (ej: reset password). |
| **Escalación** | Acción del chatbot de crear un ticket con la conversación como descripción. |
| **CSRF** | Cross-Site Request Forgery — ataque mitigado con tokens Laravel. |

---

*Documento mantenido en `docs/PRD.md`. Última actualización: 17 de abril de 2026 — v1.5.*
