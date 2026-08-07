<?php

namespace App\Http\Controllers;

use App\Models\Comunidad;
use Illuminate\Http\RedirectResponse;

class ComunidadContextoController extends Controller
{
    public function entrar(Comunidad $comunidad): RedirectResponse
    {
        abort_unless(
            auth()->user()->comunidadesAccesibles()->contains('id', $comunidad->id),
            403
        );

        session(['comunidad_actual_id' => $comunidad->id]);

        return redirect()->route('dashboard-comunidad');
    }

    public function salir(): RedirectResponse
    {
        session()->forget('comunidad_actual_id');

        return redirect()->route('dashboard');
    }
}
