<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackLastLogin
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->last_login_at === null) {
            $request->user()->updateQuietly([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);
        }

        return $next($request);
    }
}
