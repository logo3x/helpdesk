<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Webhook de deploy
    |--------------------------------------------------------------------------
    |
    | Token compartido para autenticar requests a POST /api/deploy y
    | GET /api/deploy/log. Si está vacío, los endpoints rechazan TODO
    | (fail-closed) — esto evita exponer el deploy si alguien olvida
    | setear DEPLOY_TOKEN en el .env de producción.
    |
    | Generá uno largo con `php artisan tinker --execute "echo Str::random(48);"`
    | o `openssl rand -hex 32`.
    |
    */

    'token' => env('DEPLOY_TOKEN', ''),

];
