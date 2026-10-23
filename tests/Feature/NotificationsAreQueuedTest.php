<?php

use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Los correos se envían en segundo plano: si el SMTP falla o tarda,
 * la acción del usuario no se interrumpe.
 */
it('toda notificación que envía correo se encola', function () {
    $offenders = collect(glob(app_path('Notifications/*.php')))
        ->filter(fn (string $path) => str_contains(file_get_contents($path), "'mail'"))
        ->map(fn (string $path) => 'App\\Notifications\\'.basename($path, '.php'))
        ->reject(fn (string $class) => is_subclass_of($class, ShouldQueue::class))
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});
