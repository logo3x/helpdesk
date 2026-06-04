<?php

return [
    'enabled' => env('KACTUS_ENABLED', false),

    'base_url' => env('KACTUS_BASE_URL'),
    'api_key' => env('KACTUS_API_KEY'),

    'webhook_secret' => env('KACTUS_WEBHOOK_SECRET'),

    'default_role' => env('KACTUS_DEFAULT_ROLE', 'usuario_final'),

    // Qué hacer cuando Kactus marca a un empleado como terminado:
    //   deactivate — mantiene el user, setea employment_status=terminated y bloquea login
    //   delete     — borra el user (soft delete si está habilitado)
    //   keep       — no toca al user (útil para pruebas)
    'on_terminate' => env('KACTUS_ON_TERMINATE', 'deactivate'),

    'timeout_seconds' => (int) env('KACTUS_TIMEOUT', 30),

    // Mapeo de nombres de departamento de Kactus → department_id local.
    // Se carga via .env como JSON string o se sobreescribe en runtime.
    'department_map' => json_decode(env('KACTUS_DEPARTMENT_MAP', '{}'), true) ?? [],
];
