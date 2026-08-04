<?php

namespace App\Http\Controllers;

use App\Models\EmpresaContable;
use Illuminate\Http\RedirectResponse;

class EmpresaContableContextoController extends Controller
{
    public function entrar(EmpresaContable $empresaContable): RedirectResponse
    {
        abort_unless(
            auth()->user()->empresasContablesAccesibles()->contains('id', $empresaContable->id),
            403
        );

        session(['empresa_contable_actual_id' => $empresaContable->id]);

        return redirect()->route('dashboard-contable');
    }

    public function salir(): RedirectResponse
    {
        session()->forget('empresa_contable_actual_id');

        return redirect()->route('empresas-contables.index');
    }
}
