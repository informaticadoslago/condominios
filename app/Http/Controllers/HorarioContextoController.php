<?php

namespace App\Http\Controllers;

use App\Models\Horario;
use Illuminate\Http\RedirectResponse;

class HorarioContextoController extends Controller
{
    public function entrar(Horario $horario): RedirectResponse
    {
        abort_unless(
            auth()->user()->horariosAccesibles()->contains('id', $horario->id),
            403
        );

        session(['horario_actual_id' => $horario->id]);

        return redirect()->route('dashboard-horario');
    }

    public function salir(): RedirectResponse
    {
        session()->forget('horario_actual_id');

        return redirect()->route('dashboard');
    }
}
