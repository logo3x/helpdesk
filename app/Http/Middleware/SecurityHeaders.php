<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Encabezados de seguridad para todas las respuestas.
 *
 * - HSTS: el navegador solo usa HTTPS con el dominio (solo si la
 *   petición llegó por HTTPS, para no romper entornos locales).
 * - X-Frame-Options: evita que el Helpdesk se incruste en otro sitio
 *   (clickjacking).
 * - X-Content-Type-Options / Referrer-Policy / Permissions-Policy:
 *   endurecen el navegador sin afectar el funcionamiento.
 * - Quita X-Powered-By para no revelar la versión de PHP.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;

        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        $headers->remove('X-Powered-By');

        if (function_exists('header_remove') && ! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $response;
    }
}
