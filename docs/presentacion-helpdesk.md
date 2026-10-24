# Helpdesk Confipetrol — Presentación para áreas

**Formato:** 12 slides, 25-30 minutos.
**Audiencia:** Compras, Talento Humano, Mantenimiento, Operaciones.
**Objetivo:** que al final cada área sepa cómo usar el helpdesk y cómo organizar sus propios supervisores y agentes.

---

## Slide 1 — Portada

> **Helpdesk Confipetrol**
> La plataforma única para pedir, dar y medir el soporte interno.
>
> Octubre 2026

Usar logo + robotconfipetrol.

---

## Slide 2 — ¿Por qué estamos haciendo esto?

**El problema de hoy:**
- 5 canales distintos para pedir ayuda (correo, WhatsApp, pasillo, Teams, llamada).
- Nada queda registrado.
- Nadie sabe cuánto tarda nadie.
- Las mismas 10 preguntas se repiten cada semana sin que nadie escriba la respuesta.

**Qué buscamos con el Helpdesk:**
- Una sola puerta.
- Trazabilidad: cada solicitud deja rastro.
- Medición: tiempos, cumplimiento, satisfacción.
- Memoria: lo que se resuelve una vez queda escrito.

---

## Slide 3 — Qué es el Helpdesk

Plataforma web (desktop + móvil) con:

- **Tickets** → pedidos trazables de soporte.
- **Asistente IA** → responde preguntas comunes sin crear ticket.
- **Centro de ayuda (KB)** → artículos y procedimientos.
- **Inventario** → quién tiene qué equipo.
- **Mantenimientos** → ciclos programados sobre esos equipos.

Login con cuenta Confipetrol (SSO con Microsoft).

---

## Slide 4 — Los tres roles por área

```
┌────────────┐    ┌──────────┐    ┌──────────────┐
│ Supervisor │ →  │ Agentes  │ →  │ Usuario final│
└────────────┘    └──────────┘    └──────────────┘
```

**Supervisor** (1-2 por área): gestiona el equipo, aprueba contenido, mira reportes del área.

**Agentes** (varios): toman los tickets y los resuelven.

**Usuario final** (todos): crea tickets, usa el asistente, califica.

**Importante:** los supervisores y agentes de cada área son **de esa área**, no de TI. TI solo administra la plataforma.

---

## Slide 5 — Lo que ve el usuario final

Captura del dashboard del portal:
- Arriba el asistente de IA con el saludo.
- Debajo accesos rápidos: Crear ticket, Mis activos, Centro de ayuda.
- Más abajo: mis últimos tickets.

**Tiempo esperado para abrir un ticket: 90 segundos.**

---

## Slide 6 — El ciclo de un ticket

```
Nuevo → Asignado → En progreso → Resuelto → Cerrado
                        ↓
             Pendiente cliente  ←────  (pausa el reloj SLA)
```

**Reglas:**
- Pendiente cliente pausa el reloj: no te cuenta mientras esperamos información tuya.
- Si no respondes en 7 días en Pendiente cliente, se cierra automáticamente.
- Al cerrar se envía la encuesta.

---

## Slide 7 — El asistente IA

Captura del chatbot con una conversación real.

**Qué sabe hacer:**
- Reset de contraseña, VPN, impresoras, software común.
- Preguntas de nómina, políticas, procedimientos (según el KB).
- Si no sabe, propone crear un ticket desde ahí mismo.

**Dato:** el 40% de las consultas se resuelven sin crear ticket cuando el KB está cargado.

**Qué no hacer:** no le pases contraseñas, cédulas de terceros ni datos de clientes.

---

## Slide 8 — El centro de ayuda (KB)

Captura: buscador + categorías + artículo con "me sirvió".

**Para el usuario:** busca y marca si te sirvió. Si no está, pídele al asistente.

**Para el supervisor:** escribe artículos. Hay un botón **"✨ Redactar con IA"** que te da un borrador en segundos a partir de un prompt como *"explica cómo solicitar un adelanto de nómina"*. Revisa y publica.

---

## Slide 9 — Cómo organizarse en el área

**Checklist para la jefatura del área antes de arrancar:**

- [ ] Decidir **supervisores** (1-2 personas).
- [ ] Decidir **agentes** (3-5 personas con disponibilidad real para atender).
- [ ] Hacer un listado de **categorías** del área (qué tipos de pedido reciben hoy).
- [ ] Hacer un listado de las **5-10 preguntas repetidas** → son los primeros artículos del KB.
- [ ] Mandar a TI el listado para que configure permisos.
- [ ] Hacer un ensayo de 1 semana con tickets de prueba antes de ir en vivo.

---

## Slide 10 — Qué esperamos del área

Durante el primer mes:

- **Supervisores revisan el panel 1 vez al día.**
- **Agentes atienden tickets** como parte de la jornada, no "cuando pueden".
- **Supervisor publica mínimo 3 artículos de KB** cubriendo las preguntas más repetidas.
- **Supervisor convoca una reunión al cierre del mes** para revisar métricas con TI.

El objetivo es que al tercer mes el 50% de las consultas de rutina las resuelva el asistente IA sola, y el área solo atienda los casos reales.

---

## Slide 11 — Preguntas frecuentes de la jefatura

> **"¿Esto le roba tiempo a mis agentes?"**
> No. Ya atienden estas solicitudes por mil canales distintos. Esto las concentra y las mide. El tiempo "en helpdesk" es tiempo que ya estaban dedicando.

> **"¿Mis agentes necesitan ser técnicos?"**
> No. Agentes del área = conocen el proceso del área. TI solo da la plataforma.

> **"¿Y si no me alcanza con 3-5 agentes?"**
> El reporte lo dice. Si en 2 meses el SLA del área no se cumple y el 70% de los tickets quedan colgados, hay que agregar personas. El Helpdesk te da el dato para pedirlo.

> **"¿Puedo mirar las métricas sin estar en el panel?"**
> Sí: suscripción a un reporte mensual por correo.

---

## Slide 12 — Siguientes pasos

**Esta semana:**
1. Confirmar supervisores y agentes del área con jefatura.
2. Primera lista de categorías y preguntas repetidas.

**Próxima semana:**
3. Capacitación de 1 hora con supervisores y agentes (grabada).
4. 1 semana de piloto con tickets de prueba.

**Semana 3:**
5. Go-live oficial.
6. Comunicación interna al resto del área: "a partir del lunes, los pedidos van por el Helpdesk".

**Contacto para arrancar:** mesadeayuda@confipetrol.com, asunto *"Setup área [nombre]"*.

---

## Anexo — Material entregable

- **Manual del usuario final**: `docs/manual-usuarios-areas.md` del repo (versión 1.0).
- **Esta presentación**: `docs/presentacion-helpdesk.md`.
- **Capturas de pantalla** reales con usuarios demo: pendiente generarlas durante el piloto.

---

*Para generar las versiones PDF y PPTX de estos dos documentos: `php artisan docs:export` (comando pendiente) o usar Pandoc en el PC.*
