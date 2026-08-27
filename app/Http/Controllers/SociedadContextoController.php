<?php

namespace App\Http\Controllers;

use App\Models\Sociedad;
use Illuminate\Http\RedirectResponse;

class SociedadContextoController extends Controller
{
    public function entrar(Sociedad $sociedad): RedirectResponse
    {
        abort_unless(
            auth()->user()->sociedadesAccesibles()->contains('id', $sociedad->id),
            403
        );

        session(['sociedad_actual_id' => $sociedad->id]);

        return redirect()->route('dashboard-sociedad');
    }

    public function salir(): RedirectResponse
    {
        session()->forget('sociedad_actual_id');

        return redirect()->route('dashboard');
    }
}
