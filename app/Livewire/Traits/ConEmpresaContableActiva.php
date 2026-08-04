<?php

namespace App\Livewire\Traits;

use App\Models\EmpresaContable;

trait ConEmpresaContableActiva
{
    protected function empresaContableActual(): ?EmpresaContable
    {
        return EmpresaContable::find(session('empresa_contable_actual_id'));
    }
}
