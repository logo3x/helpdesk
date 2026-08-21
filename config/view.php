<?php

return [
    /*
    |--------------------------------------------------------------------------
    | View Storage Paths
    |--------------------------------------------------------------------------
    |
    | Los paths donde Laravel busca los .blade.php.
    */
    'paths' => [
        resource_path('views'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compiled View Path
    |--------------------------------------------------------------------------
    |
    | En producción sobre Windows/IIS el pool del sitio a veces no puede
    | hacer `rename()` en storage/framework/views/ por conflictos de ACL
    | heredadas o por antivirus escaneando temp files. Movemos el path a
    | bootstrap/cache/views/ (donde IIS ya escribe route:cache/config:cache
    | sin problemas). Se puede sobreescribir con VIEW_COMPILED_PATH en .env.
    */
    'compiled' => env(
        'VIEW_COMPILED_PATH',
        realpath(storage_path('framework/views')) ?: storage_path('framework/views'),
    ),
];
