<?php

use App\Http\Middleware\ForcePasswordChange;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Permite que /api/* lea la cookie de sesión cuando el dominio
        // está en SANCTUM_STATEFUL_DOMAINS. Sin esto, el web-scan del
        // portal (auth:sanctum) no encuentra al usuario logueado.
        $middleware->statefulApi();

        // Fuerza cambio de password en el primer login de cuentas
        // locales precargadas (password_must_change=1). Se aplica al
        // grupo web para que también atrape los paneles Filament
        // — el middleware tiene guards internos para no formar loop
        // ni bloquear rutas del propio flujo (livewire, logout, etc.).
        $middleware->web(append: [
            ForcePasswordChange::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
