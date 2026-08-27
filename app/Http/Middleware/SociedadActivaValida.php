<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puerta de las rutas de sociedad: hace falta una sociedad activa en sesión, y
 * el usuario tiene que tener acceso a ella de verdad (rol "global-sociedad" o su
 * propio "sociedad-{id}"), no basta con que el id esté en la sesión.
 */
class SociedadActivaValida
{
    public function handle(Request $request, Closure $next): Response
    {
        $sociedadId = session('sociedad_actual_id');

        if (! $sociedadId) {
            return redirect()->route('dashboard');
        }

        if (! auth()->user()->sociedadesAccesibles()->contains('id', $sociedadId)) {
            session()->forget('sociedad_actual_id');

            abort(403, __('No tienes acceso a esa sociedad.'));
        }

        return $next($request);
    }
}
