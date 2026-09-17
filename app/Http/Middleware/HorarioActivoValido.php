<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puerta de las rutas de horario: hace falta un horario activo en sesión, y el
 * usuario tiene que tener acceso a él de verdad (rol "global" o su propio
 * "horario-{id}"), no basta con que el id esté en la sesión.
 */
class HorarioActivoValido
{
    public function handle(Request $request, Closure $next): Response
    {
        $horarioId = session('horario_actual_id');

        if (! $horarioId) {
            return redirect()->route('dashboard');
        }

        if (! auth()->user()->horariosAccesibles()->contains('id', $horarioId)) {
            session()->forget('horario_actual_id');

            abort(403, __('No tienes acceso a ese horario.'));
        }

        return $next($request);
    }
}
