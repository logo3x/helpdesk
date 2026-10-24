# Manual de usuario — Helpdesk Confipetrol

**Para áreas: Compras, Talento Humano, Mantenimiento, Operaciones y toda área que no sea TI.**

**Versión:** 1.0 — 2026-10-24

---

## 1. Qué es el Helpdesk

Es la plataforma única por la que **pides ayuda al equipo de TI** (y a otras áreas de soporte internas), consultas el **estado de tus solicitudes**, buscas respuestas en el **centro de ayuda** y hablas con el **asistente virtual**.

Reemplaza las vías dispersas (WhatsApp, correo suelto, llamada telefónica, pedir por el pasillo) por una sola ruta trazable: cada solicitud queda registrada, nadie se olvida, y los tiempos se miden.

### Lo que puedes hacer

- Abrir un ticket y adjuntar lo necesario (foto del error, pantallazo, archivo).
- Preguntarle al asistente virtual antes de abrir un ticket (muchas veces responde al instante).
- Ver tus activos asignados y confirmar la recepción de un equipo.
- Consultar artículos del centro de ayuda.
- Calificar la atención cuando el ticket se cierra.

---

## 2. Cómo acceder

Dos caminos:

1. **SSO con tu cuenta Confipetrol** (recomendado). Entra a la URL del Helpdesk y pulsa **"Iniciar sesión con Microsoft"**. Usas la misma contraseña del correo. Si tienes 2FA, te la pedirá como siempre.
2. **Usuario y contraseña del Helpdesk** si TI te creó una cuenta local.

La primera vez que entras aparece el **Acuerdo de Servicio (ASL)**. Léelo, marca la casilla y pulsa **"Aceptar y continuar"**. Es un paso único.

---

## 3. El flujo del usuario final

### 3.1 Crear un ticket

1. En el menú lateral, **Crear ticket**.
2. **Asunto** corto (ej: "No puedo imprimir en la impresora del 3er piso").
3. **Descripción** detallada: qué estabas haciendo, qué viste exactamente, qué mensaje de error apareció. Si es visual, adjunta captura.
4. **Categoría**: elige la más cercana. Al elegir una categoría verás un aviso con el **departamento** que atenderá tu ticket. Si no estás seguro de la categoría, deja que el asistente te sugiera una.
5. **Impacto** y **Urgencia**:
   - *Bajo / Media* para cosas que no te bloquean (molestan pero puedes seguir).
   - *Alto / Alta* para cosas que te paran el trabajo o afectan al equipo.
   - No infles el impacto para "pasar primero": romperás la cola para los que de verdad están parados.
6. Pulsa **Enviar**. Recibes un número de ticket (`TK-2026-...`) y un correo de confirmación.

### 3.2 Ver tus tickets

**Menú → Mis tickets.**

Verás una lista con el estado de cada uno. Los estados posibles:

| Estado | Significa |
|--------|-----------|
| **Nuevo** | Lo acabas de crear, nadie lo tomó aún. |
| **Asignado** | Un agente lo tomó. |
| **En progreso** | El agente está trabajando en él. |
| **Pendiente cliente** | **Necesitan algo de ti**: una respuesta, un dato, una autorización. Hasta que no respondas, el SLA se pausa. |
| **Resuelto** | El agente dice que ya está. Puedes reabrir si no es así. |
| **Cerrado** | Pasaron unos días desde resuelto sin novedad; se cerró automático. |

### 3.3 Responder a un ticket

Dentro del ticket puedes agregar comentarios. Si el ticket está **Pendiente cliente**, pulsa el botón para enviar la información que te están pidiendo. Cada comentario queda registrado con fecha y usuario.

### 3.4 La encuesta de satisfacción

Cuando el ticket se cierra, recibes un correo con un enlace a la encuesta. Son 6 preguntas rápidas (atención, contacto, resolución, tiempo, conocimiento, actitud) con 5 estrellas cada una. Si no respondes en 7 días, se asume positiva.

**Por qué importa**: con esos datos el supervisor identifica agentes que necesitan apoyo o reconocimiento, y categorías de servicio que están fallando.

---

## 4. El asistente virtual (IA)

Es la cara en forma de robot que aparece arriba del portal. Está diseñado para resolver preguntas comunes **sin tener que crear un ticket**.

### 4.1 Qué le puedes preguntar

- "¿Cómo reseteo mi contraseña del correo?"
- "No me conecta la VPN."
- "¿Cómo imprimo en doble cara desde el portátil?"
- "Necesito instalar X software."
- "¿Dónde descargo la plantilla de inducción?"

### 4.2 Qué hace

1. Busca en el **centro de ayuda** (KB) la respuesta.
2. Si no la encuentra, consulta a un modelo de IA externo (siempre con las limitaciones del ASL que firmaste).
3. Si tampoco así resuelve, te ofrece **crear un ticket directamente** sin cambiar de pantalla.

### 4.3 Qué no decirle

- Contraseñas en claro.
- Números de tarjeta de crédito, cuentas bancarias, cédulas de terceros.
- Información confidencial de clientes.

El ASL ya lo dice. El asistente puede quedar registrado y es procesado por un proveedor externo.

### 4.4 Calificar la respuesta

Debajo de cada respuesta del bot hay un pulgar arriba / pulgar abajo. Úsalo sin miedo: así el equipo mejora el centro de ayuda y el prompt del modelo. Los pulgares abajo generan tickets internos para revisar la respuesta.

---

## 5. Roles por área

Cada área tiene tres figuras dentro del Helpdesk. **Todos los supervisores y agentes son usuarios de las áreas, no de TI.** TI solo administra la plataforma.

### 5.1 Supervisor (uno o dos por área)

Es quien **gestiona el equipo de agentes** de su área:

- Reasignar tickets dentro del equipo.
- Aprobar o rechazar artículos del centro de ayuda que proponen los agentes.
- Ver reportes operativos: SLA del área, carga por agente, categorías más frecuentes.
- Marcar prioridad crítica o revisar un ticket mal clasificado.
- Crear usuarios **agentes** dentro de su área.

El supervisor **no ve tickets de otras áreas**. Si Compras tiene supervisora, ella ve todo lo de Compras pero no lo de Talento Humano.

### 5.2 Agentes (varios por área)

Son quienes **atienden tus tickets**:

- Toman el ticket, lo analizan, lo trabajan.
- Comentan contigo si necesitan información.
- Resuelven y cierran.
- Pueden transferir a otra área si el ticket salió mal direccionado.

### 5.3 Usuario final (tú, si lees esto)

- Creas tickets, consultas tus activos, calificas la atención.
- Si estás en un área con supervisor + agentes, ellos son quienes te atienden.

---

## 6. Centro de ayuda (KB)

**Menú → Centro de ayuda.**

Es un buscador de artículos: guías paso a paso, procedimientos, resuelve-tú-mismo. Están filtrados por departamento, así que ves los relevantes.

### 6.1 Buscar

Teclea la palabra clave. Los más vistos aparecen arriba. Entra, lee, y arriba del todo marca **"Me sirvió"** o **"No me sirvió"** para que el equipo sepa qué está funcionando.

### 6.2 ¿Y si no encuentro lo que busco?

Pídele al asistente virtual. Si realmente no está, se registra como un **"gap de KB"** para que el equipo lo escriba.

---

## 7. Para supervisores: crear artículos del centro de ayuda

Esta sección es solo para quienes tienen el rol **supervisor** o **editor KB** en su área.

### 7.1 Crear un artículo

1. Panel `/soporte` → **Centro de ayuda** → **Nuevo artículo**.
2. Elige **departamento** y **categoría**.
3. Escribe el **título** claro ("Cómo solicitar un adelanto de nómina").
4. En el cuerpo usa **Markdown**: titulares con `#`, listas con `-`, negritas con `**`.
5. Marca **"Publicar"** si estás seguro, o **"Solicitar publicación"** para que otro supervisor lo apruebe (recomendado para contenido con impacto).

### 7.2 Usar el asistente IA para redactar

En el formulario, pulsa **"✨ Redactar con IA"**: cuéntale el tema y tu público en lenguaje natural, elige el **tono** (formal / amigable / técnico), y la IA te da un borrador. **Siempre revísalo antes de publicar**: puede equivocarse con información específica de Confipetrol.

### 7.3 Aprobar o rechazar

Si eres supervisor y recibes una solicitud:

- **Aprobar y publicar**: el artículo queda visible.
- **Rechazar con motivo**: lo devuelves a borrador con una nota al autor para que corrija.

---

## 8. Buenas prácticas

### Al crear un ticket

- **Asunto corto y específico.** "No funciona" no sirve. "No carga el detalle de la factura en SAP cuando filtro por fecha" sí.
- **Un ticket, un problema.** No metas tres cosas en el mismo ticket aunque sean parecidas.
- **Adjunta lo que puedas**: captura del error, logs, foto del equipo. Es la diferencia entre resolver en 10 minutos o en 2 días.
- **Responde pronto en Pendiente cliente**. Si no respondes, el ticket se te cierra solo y nadie lo retoma.

### Al pedir ayuda al asistente

- **Pregunta como hablas, no como código.** El asistente lee lenguaje natural.
- **Si una respuesta no sirve**, dilo con pulgar abajo. Ahí mejora.

### Al calificar

- **No pongas 5 por costumbre.** El 3 es información útil: dice "funcionó pero algo faltó".
- **El comentario importa**. Es anónimo para el agente, lo lee el supervisor.

---

## 9. Preguntas frecuentes

**P: ¿Puedo crear un ticket en nombre de otra persona?**
R: No, el ticket queda asociado a quien lo crea. Si alguien de tu equipo no tiene acceso, abre el ticket a tu nombre y menciona el nombre y correo del afectado en la descripción.

**P: ¿Qué pasa si mi ticket queda sin atender?**
R: El motor SLA avisa al supervisor cuando un ticket se acerca al límite de tiempo. Si pasa el 100% sin respuesta, el supervisor recibe una alerta.

**P: ¿Puedo cambiar la categoría después de crear?**
R: No directamente. Déjale al agente la posibilidad de **transferir** a otra área o categoría si ves que es necesario; él lo hace con un click.

**P: ¿Veo tickets de mis compañeros?**
R: No. Cada usuario ve solo lo que creó él. Si eres supervisor, ves los de todo tu equipo del área.

**P: Un activo me aparece en Mis activos y no es mío.**
R: Avisa a TI con un ticket categorizado como "Inventario → corrección de custodio".

---

## 10. Contactos

- **Mesa de ayuda central (TI):** mesadeayuda@confipetrol.com — por fuera del horario de la plataforma, usar este correo.
- **Problemas con el acceso al Helpdesk:** mismo correo con asunto "SIN ACCESO".
- **Supervisor de tu área:** lo encuentras en Menú → tu perfil → "Mi área".

---

## 11. Cambios en esta versión

| Versión | Fecha | Cambios |
|---------|-------|---------|
| 1.0 | 2026-10-24 | Primera versión del manual para áreas no-TI. |

---

*Documento mantenido por el equipo de TI de Confipetrol. Si encuentras algo que no coincide con lo que ves en pantalla, por favor abre un ticket con categoría "Documentación → corrección de manual".*
