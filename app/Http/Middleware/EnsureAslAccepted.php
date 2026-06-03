<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Si el usuario autenticado no ha aceptado el ASL (asl_accepted_at
 * es null), lo redirige a /asl/accept guardando la URL original como
 * intended() para volver tras aceptar.
 *
 * Solo aplica a peticiones GET para no romper POSTs (form submits,
 * APIs, etc.).
 */
class EnsureAslAccepted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->asl_accepted_at !== null) {
            return $next($request);
        }

        if (! $request->isMethod('GET')) {
            return $next($request);
        }

        // No interceptar la propia ruta de aceptación ni Livewire
        // updates (que viajan por POST a /livewire/update).
        if ($request->routeIs('asl.show') || $request->routeIs('asl.accept') || $request->routeIs('logout')) {
            return $next($request);
        }

        return redirect()->guest(route('asl.show'));
    }
}
