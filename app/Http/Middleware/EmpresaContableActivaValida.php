<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puerta de las rutas de gestión contable: hace falta una empresa contable
 * activa en sesión, y el usuario tiene que tener acceso a ella de verdad (rol
 * "global" o su propio "empresa-contable-{id}"), no basta con que el id esté en
 * la sesión.
 */
class EmpresaContableActivaValida
{
    public function handle(Request $request, Closure $next): Response
    {
        $empresaContableId = session('empresa_contable_actual_id');

        if (! $empresaContableId) {
            return redirect()->route('empresas-contables.index');
        }

        if (! auth()->user()->empresasContablesAccesibles()->contains('id', $empresaContableId)) {
            session()->forget('empresa_contable_actual_id');

            abort(403, __('No tienes acceso a esa empresa contable.'));
        }

        return $next($request);
    }
}
