<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bootstrap Admin
    |--------------------------------------------------------------------------
    |
    | Credentials for the super_admin user created by DatabaseSeeder. These
    | are only used when running `php artisan db:seed` and are never exposed
    | at runtime. Password must be set via env; if empty, a random one is
    | generated and printed to the console during seeding.
    |
    */
    'seed_admin' => [
        'email' => env('SEED_ADMIN_EMAIL', 'admin@confipetrol.local'),
        'name' => env('SEED_ADMIN_NAME', 'Administrador'),
        'password' => env('SEED_ADMIN_PASSWORD'),
    ],

];
