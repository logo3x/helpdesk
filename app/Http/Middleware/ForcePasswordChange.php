<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirige al usuario a /password/first-change si su cuenta local fue
 * creada con la password inicial predecible (primeros 8 dígitos de la
 * cédula) y todavía no la cambió. Cubre el caso "personas precargadas
 * como cuenta local" del Sprint 2.
 *
 * NO aplica a:
 *   - Usuarios entrando por Azure (ellos no tienen password local).
 *   - Rutas del propio flujo de cambio (evita loop).
 *   - Rutas de logout, livewire updates y assets.
 */
class ForcePasswordChange
{
    /**
     * Rutas que quedan siempre habilitadas para no bloquear el flujo.
     * Se comparan con Str::is (soporta wildcards).
     *
     * @var array<int, string>
     */
    protected const EXCLUDED_PATHS = [
        'password/first-change',
        'password/first-change/*',
        'logout',
        'auth/azure/*',
        'livewire/*',
        'filament/*',
        'storage/*',
        'up',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->password_must_change) {
            return $next($request);
        }

        // Rutas siempre permitidas (para no dejar al user encerrado).
        foreach (self::EXCLUDED_PATHS as $pattern) {
            if ($request->is($pattern)) {
                return $next($request);
            }
        }

        return redirect()->route('password.first-change')
            ->with('status', 'Por seguridad, cambiá tu contraseña temporal antes de continuar.');
    }
}
