# Actualizaciones del Helpdesk — 30 de octubre al 6 de noviembre de 2026

**Para:** equipo de TI, supervisores y administradores del Helpdesk.
**Versión desplegada:** `ed01529` (6 de noviembre de 2026).
**En una línea:** los tickets se ven y se atienden mejor, el SLA ahora avisa a tiempo, el asistente IA responde de forma más útil y el sistema se respalda solo todas las noches.

Cada punto trae un **Cómo comprobarlo**: son pruebas cortas para hacer en producción.

---

## 1. Tickets

### 1.1 Vista del ticket rediseñada
- La información va en filas a lo ancho, en este orden: resumen, descripción, conversación, adjuntos, SLA y clasificación.
- **Descripción:** indica de dónde vino el ticket (*Creado en el portal* o *Escalado desde el asistente virtual*). Si vino del asistente, muestra destacado el problema reportado, los datos de la sesión, la prioridad sugerida por la IA y la conversación plegada.
- **Conversación tipo chat:** los mensajes del solicitante van a la izquierda y los del equipo a la derecha. Las notas internas se ven en ámbar y los eventos del sistema (asignado, trasladado, recordatorio, cierre) como líneas discretas.
- **Botón Responder:** incluye respuestas predefinidas y la opción *esperar respuesta del cliente*.

**Cómo comprobarlo:** en `/soporte`, abre cualquier ticket escalado desde el asistente. Debes ver el problema destacado arriba y el enlace *Ver conversación con el asistente*.

### 1.2 Lista de tickets
- El botón **Ver** abre el ticket en una ventana sobre la lista, sin cambiar de página.
- Para supervisores, al hacer **clic en la fila** se abre directamente **Editar**.
- Se corrigió el **error 500** que salía en `/admin/tickets`.

**Cómo comprobarlo:** en la lista de tickets, pulsa *Ver*: se abre una ventana. Ciérrala y haz clic en la fila: se abre la edición.

### 1.3 Adjuntos más seguros y fáciles de ver
- Los adjuntos se muestran como tarjetas con vista previa. Al pulsarlos se abren en una ventana (imágenes y PDF) sin salir del ticket.
- Los archivos ahora se guardan en una **carpeta privada**. Solo los puede abrir quien tiene permiso para ver el ticket. Antes, cualquiera con el enlace podía descargarlos.
- Esto aplica en los paneles admin y soporte y en el portal.

**Cómo comprobarlo:**
1. Abre un adjunto desde un ticket: se ve en la ventana.
2. Copia su enlace y ábrelo en una ventana de incógnito: debe pedir iniciar sesión.

### 1.4 Seguimiento automático cuando el cliente no responde
Cuando el agente responde con *esperar respuesta del cliente*, el ticket pasa a **Pendiente cliente** y **el SLA se pausa**. Si el cliente no contesta, todo ocurre en un día hábil (de 08:00 a 18:00):

| Horas hábiles en espera | Qué pasa |
|---|---|
| 3 h | Recordatorio al cliente |
| 7 h | Último aviso |
| 10 h | Cierre automático con mensaje explicativo, **sin encuesta** |

Si el cliente responde, el ticket vuelve a *En progreso* y el SLA se reanuda.

Otros automatismos:
- Los tickets **Resueltos** se cierran solos a los **2 días**, con un mensaje que explica el cierre.
- Las encuestas sin responder se marcan como positivas al **día siguiente**.

**Cómo comprobarlo:** deja un ticket de prueba en *Pendiente cliente*. Al rato verás en la conversación el evento *⏰ Recordatorio al cliente*, y el solicitante lo recibirá por correo.

---

## 2. SLA

### 2.1 Las alertas ahora avisan a alguien
Antes, las alertas del SLA solo quedaban registradas y nadie se enteraba. Ahora:

| Nivel | Quién recibe el aviso | Por dónde |
|---|---|---|
| 70% del plazo | Agente asignado (si no hay, los supervisores del departamento) | Campanita |
| 90% del plazo | Igual que el 70% | Campanita y correo |
| Incumplido | Agente asignado y supervisores del departamento | Campanita y correo |

- Si en una misma revisión un ticket cruza varios niveles, solo llega el aviso del más alto, y el mismo aviso nunca se repite.
- Se puede apagar con `SLA_ALERTS_ENABLED=false` en el `.env`. Con `SLA_ALERTS_NOTIFY_ADMINS=true` los administradores también reciben los incumplimientos.

**Cómo comprobarlo:** en **Reporte SLA → Últimas escalaciones**, cada alerta nueva muestra *Notificado: [nombre]*. Esa persona debe tener el aviso en su campanita.

### 2.2 La espera del cliente ya no consume el plazo
Un ticket en *Pendiente cliente* seguía contando tiempo mientras esperaba. Eso generaba alertas falsas. Ya no cuenta.

**Cómo comprobarlo:** un ticket que pase horas en *Pendiente cliente* no debe generar alertas del 70% ni del 90% mientras espera.

### 2.3 Reporte SLA
- Los paneles admin y soporte usan ahora el mismo reporte, así que siempre muestran lo mismo.
- **Supervisores:** solo ven los datos de su departamento, en pantalla, en el PDF y en el Excel.
- **Exportar Consolidado (Excel):**
  - Administradores: descargan el de toda la empresa.
  - Supervisores: descargan el de su departamento. La cabecera del Excel indica el departamento.
- Los textos de ayuda (íconos **?**) se actualizaron en todos los paneles con las definiciones de pausa, tiempo de solución, *Resuelto → Cerrado* y alertas.

**Cómo comprobarlo:** entra como supervisor a **Soporte → Reporte SLA**, pulsa *Exportar Consolidado (Excel)* y revisa que la cabecera diga tu departamento.

---

## 3. Asistente virtual (IA)

- **Respuestas más personales:** cuando encuentra un artículo de la base de conocimiento, el asistente responde a la pregunta concreta del usuario en lugar de pegar el artículo completo.
- **Guía paso a paso:** hace preguntas con **botones de respuesta** (p. ej. *¿El equipo enciende?* → Sí / No) para orientar al usuario.
- **Prioridad sugerida:** al escalar a ticket, la IA propone impacto y urgencia (como máximo *Alta*). La sugerencia queda visible en la descripción del ticket.
- **Sugerencias de artículos:** cuando varias personas preguntan algo que la base de conocimiento no responde, el sistema redacta un borrador de artículo y avisa a los supervisores del área y a los administradores para que lo revisen.
- **Vigilante del modelo:** si el modelo de IA deja de responder, el sistema cambia solo a otro que funcione. Cuando el preferido vuelve, regresa a él. Cada cambio se avisa en la campanita (*IA: cambio automático de modelo*).

**Cómo comprobarlo:**
1. En el portal, pregunta algo que tenga artículo en la base de conocimiento: la respuesta debe referirse a tu pregunta.
2. Escribe un problema que requiera diagnóstico: deben aparecer botones de respuesta.

---

## 4. Base de conocimiento (KB)

- El editor muestra la **vista previa arriba**. El editor de Markdown queda plegado y el formulario va en filas que se pueden colapsar.
- **Redactar con IA** aparece al crear un artículo. **Refinar con IA** aparece al editar uno existente.

**Cómo comprobarlo:** edita un artículo de la base de conocimiento: arriba debe verse la vista previa y el botón debe decir *Refinar con IA*.

---

## 5. Bitácora de actividad (nuevo)

- Registra los **inicios de sesión** y las **acciones de los usuarios** (crear, editar, eliminar, cambiar estado), con filtros por persona, acción y fecha.
- La ven administradores y supervisores.
- Es rápida aun con muchos registros: la página carga en menos de 0,1 s con 100.000 registros. Los registros de más de un año se borran solos cada mes.

**Cómo comprobarlo:** en `/admin`, entra a **Bitácora**, filtra por un agente y revisa sus últimos ingresos y acciones.

---

## 6. Encuestas

- El **informe unificado de encuestas** (tickets y mantenimientos) está ahora también en el panel admin, con una interfaz reorganizada.

**Cómo comprobarlo:** en `/admin`, abre **Encuestas (todas)** y filtra por tipo y departamento.

---

## 7. Respaldos automáticos (nuevo)

| Respaldo | Cuándo | Aviso |
|---|---|---|
| Base de datos | Todos los días a la 01:30 | Campanita al terminar; **correo solo si falla** |
| Completo (archivos y base de datos) | Domingos a las 02:30 | Correo a los administradores con la base de datos adjunta (si cabe) y un enlace de descarga del completo, válido 7 días |

- Se guardan en `C:\Respaldos\helpdesk`. Los antiguos se limpian solos.
- Los archivos van **cifrados con contraseña (AES-256)**. Se abren con **7-Zip o WinRAR**; el extractor de Windows no los abre y muestra el error `0x80004005`.
- La página **Admin → Configuración → Respaldos** muestra el estado de cada tipo, los respaldos guardados y los botones *Respaldar BD ahora* y *Respaldo completo ahora*.

**Cómo comprobarlo:**
1. En la página Respaldos, la tarjeta *Base de datos (diario)* debe decir **Sano** con el respaldo de la madrugada.
2. Descarga un respaldo y ábrelo con WinRAR: debe pedir la contraseña.
3. El lunes 9 de noviembre, revisa que llegó el correo del respaldo completo del domingo.

---

## 8. Seguridad

- Se agregaron encabezados de seguridad del navegador.
- El correo por Microsoft Graph solo puede enviar desde el remitente configurado.
- Los adjuntos de tickets ahora son privados (ver punto 1.3).
- Se reforzaron la bitácora, el chatbot y las sugerencias de KB frente a contenido malicioso: el texto que escriben los usuarios se limpia antes de mostrarse o enviarse por correo.

---

## 9. Servidor

- **Procesos de fondo instalados:** dos tareas programadas de Windows mantienen el sistema al día.
  - *Helpdesk - Laravel Scheduler* corre cada minuto y ejecuta las tareas automáticas: respaldos, recordatorios, cierres, alertas SLA, vigilante de IA y sugerencias de KB.
  - *Helpdesk - Queue Worker* envía los correos en segundo plano. Llegan en menos de un minuto.
- Se descartó NSSM, porque Microsoft Defender lo bloquea. La guía está en `docs/queue-worker-windows.md`.
- Los paneles tienen su propio tema visual compilado, con el que se ven bien todas las pantallas nuevas.

**Cómo comprobarlo**, en PowerShell en el servidor:
```powershell
Get-ScheduledTask -TaskName 'Helpdesk*' | Select-Object TaskName, State
```
El *Queue Worker* debe aparecer en **Running**.

---

## 10. Pendientes

| Pendiente | Responsable | Cuándo |
|---|---|---|
| Cambiar la contraseña de los respaldos por una fuerte y guardarla fuera del servidor | TI | Esta semana |
| Borrar los respaldos marcados como *Anterior* | TI | Después de confirmar los nuevos |
| Borrar carpetas de PHP en desuso | TI | Desde el 6 de noviembre, si ningún sitio falla |
| Quitar la red de seguridad temporal del correo (`MAIL_FAIL_SAFE=false`) | TI | Tras unos días sin errores de correo |
| Limitar la app de Azure a su propio buzón | Administrador de Microsoft 365 | Por agendar |
| Definir el proveedor de IA definitivo con acuerdo de datos (DPA) | Dirección | Por definir |
| Renovar el secreto de Azure | TI | Antes de octubre de 2028 |
