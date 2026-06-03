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
    'auto_close_days' => (int) env('TICKETS_AUTO_CLOSE_DAYS', 7),

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
    'csat_auto_positive_days' => (int) env('TICKETS_CSAT_AUTO_POSITIVE_DAYS', 7),
];
