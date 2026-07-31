<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puerta de las rutas de comunidad: hace falta una comunidad activa en sesión, y
 * el usuario tiene que tener acceso a ella de verdad (rol "global" o su propio
 * "comunidad-{id}"), no basta con que el id esté en la sesión.
 */
class ComunidadActivaValida
{
    public function handle(Request $request, Closure $next): Response
    {
        $comunidadId = session('comunidad_actual_id');

        if (! $comunidadId) {
            return redirect()->route('dashboard');
        }

        if (! auth()->user()->comunidadesAccesibles()->contains('id', $comunidadId)) {
            session()->forget('comunidad_actual_id');

            abort(403, __('No tienes acceso a esa comunidad.'));
        }

        return $next($request);
    }
}
