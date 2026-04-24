# Pendientes — Helpdesk Confipetrol

**Última actualización:** 2026-04-21 (v1.7)

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
- Supervisord o systemd para mantenerlo activo.

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

### 3. Notificaciones en tiempo real (campanita en portal)

**Estado hoy:** notificaciones se guardan en tabla `notifications` pero no hay UI para verlas en `/portal`.

**Qué hace falta:**
- Agregar componente Livewire en el header del portal que muestre badge de no-leídas.
- Dropdown con lista de notificaciones recientes.
- Marcar como leída al hacer click.
- Link directo al ticket relacionado.

---

## 🟢 P2 — Mejoras deseables

### 4. Flujo de aprobación de KB articles

**Estado hoy:** agente crea en status="draft", supervisor puede publicar directamente. No hay workflow formal.

**Qué hace falta:**
- Botón "Solicitar publicación" en edit de KB (para agente).
- Notificación al supervisor cuando hay pendiente de aprobación.
- Vista "KB pendientes de aprobar" para supervisor.
- Log de aprobaciones con comentario opcional.

### 5. Búsqueda de KB desde el portal

**Estado hoy:** el chatbot tiene RAG pero el usuario no puede buscar KB directamente.

**Qué hace falta:**
- Agregar `/portal/kb` con buscador y lista de KBs públicas.
- Filtro por departamento / categoría.
- Contador de vistas + botón "útil / no útil".

### 6. Reporte SLA exportable a PDF

**Estado hoy:** Reporte SLA solo se muestra en pantalla.

**Qué hace falta:**
- Botón "Exportar a PDF" que genere un informe ejecutivo mensual.
- Diseño con logo Confipetrol + gráficas.
- Programar envío automático el 1° de cada mes al buzón de Gerencia TI.

### 7. Dashboard para supervisores

**Estado hoy:** todos los dashboards de `/soporte` muestran datos globales.

**Qué hace falta:**
- Filtrar automáticamente los widgets por depto del supervisor logueado.
- Stat adicional: "Agentes de mi depto" (activos / libres / saturados).
- Ranking de agentes por productividad (tickets resueltos / CSAT / tiempo respuesta).

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

---

*Este documento se actualiza en cada release. Prioridades revisables.*
