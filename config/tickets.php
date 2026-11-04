<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Auto-close de tickets resueltos
    |--------------------------------------------------------------------------
    |
    | Días que un ticket en estado "Resuelto" puede permanecer sin actividad
    | antes de que AutoCloseTicketsJob lo cierre automáticamente y dispare
    | la encuesta de satisfacción.
    |
    | Si el solicitante reabre, comenta o se actualiza el ticket en este
    | período, no se cierra (porque el estado cambia o el filtro vuelve a
    | empezar a contar desde resolved_at).
    */
    'auto_close_days' => (int) env('TICKETS_AUTO_CLOSE_DAYS', 2),

    /*
    |--------------------------------------------------------------------------
    | Auto-positiva en encuesta de satisfacción
    |--------------------------------------------------------------------------
    |
    | Días tras enviar la encuesta CSAT antes de que AutoMarkSurveysPositiveJob
    | la marque como 5 estrellas automáticamente. El silencio del cliente se
    | interpreta como conformidad. El comment de la encuesta queda marcado
    | con "(auto-positiva: cliente no respondió en X días)" para diferenciar
    | en reportes.
    */
    'csat_auto_positive_days' => (int) env('TICKETS_CSAT_AUTO_POSITIVE_DAYS', 1),

    /*
    |--------------------------------------------------------------------------
    | Auto-positiva en encuesta de mantenimiento
    |--------------------------------------------------------------------------
    |
    | Días tras enviar la encuesta de mantenimiento antes de que
    | AutoMarkMaintenanceSurveysPositiveJob la marque como 5 estrellas
    | automáticamente.
    */
    'maintenance_csat_auto_positive_days' => (int) env('MAINTENANCE_CSAT_AUTO_POSITIVE_DAYS', 1),

    /*
    |--------------------------------------------------------------------------
    | Tickets esperando respuesta del cliente (estado "Pendiente cliente")
    |--------------------------------------------------------------------------
    |
    | Mientras el ticket espera al cliente, el SLA está en pausa. Si el
    | cliente no responde, AwaitingCustomerFollowUpJob (cada 15 min en
    | horario laboral) le envía recordatorios y al final cierra el ticket
    | (sin encuesta, porque no hubo solución). Las horas son HÁBILES
    | (lunes a viernes, 08:00–18:00) contadas desde que el ticket pasó a
    | "Pendiente cliente". Si el cliente responde, el ticket vuelve a
    | "En progreso" y el SLA se reanuda.
    |
    | Por defecto todo ocurre en 1 día hábil (10 h): recordatorio a las 3 h,
    | último aviso a las 7 h y cierre a las 10 h.
    |
    | En los mensajes se puede usar :name (nombre del cliente), :number
    | (número de ticket) y :hours (horas hábiles restantes para el cierre).
    */
    'awaiting_customer' => [
        'reminder_hours' => [3, 7],
        'close_hours' => (int) env('TICKETS_AWAITING_CUSTOMER_CLOSE_HOURS', 10),

        'messages' => [
            'reminder' => "Hola :name 👋\n\nSeguimos esperando tu respuesta para continuar con el ticket **:number**. "
                ."Cuando puedas, respóndenos por aquí con la información que te pedimos.\n\n"
                .'Si no recibimos respuesta en las próximas **:hours horas hábiles**, cerraremos el ticket automáticamente.',

            'last_reminder' => "Hola :name,\n\nEste es el **último aviso** del ticket **:number**: aún no recibimos tu respuesta. "
                ."Si no respondes, el ticket se cerrará automáticamente en unas **:hours horas hábiles**.\n\n"
                .'Si ya no necesitas ayuda, no tienes que hacer nada.',

            'closed' => "Hola :name,\n\nCerramos el ticket **:number** porque no recibimos respuesta después de los recordatorios.\n\n"
                .'Si el problema continúa, crea un ticket nuevo desde el portal e indica este número (**:number**) para retomar el caso.',

            // Cierre automático de tickets RESUELTOS (auto_close_days).
            'resolved_closed' => "Hola :name,\n\nCerramos el ticket **:number** porque se marcó como resuelto y no recibimos "
                ."comentarios en los últimos días. ¡Esperamos que todo funcione bien!\n\n"
                .'Si el problema vuelve a aparecer, crea un ticket nuevo e indica este número (**:number**).',
        ],
    ],
];
