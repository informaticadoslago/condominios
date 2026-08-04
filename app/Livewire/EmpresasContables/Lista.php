<?php

namespace App\Livewire\EmpresasContables;

use App\Livewire\ListaComponent;
use App\Models\EmpresaContable;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{
    public function mount()
    {
        $this->sort      = 'razon_social';
        $this->direction = 'asc';
    }

    #[On('empresa-contable-guardada')]
    public function refrescar()
    {
        // el evento fuerza el re-render de la lista
    }

    public function render()
    {
        $search = trim($this->search ?? '');

        $items = EmpresaContable::when($search, function ($q) use ($search) {
            $q->where('razon_social', 'like', "%{$search}%")
                ->orWhere('cif', 'like', "%{$search}%");
        })
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->lineasXPagina);

        return view('livewire.empresas-contables.lista', [
            'items' => $items,
            'empresaContableActualId' => session('empresa_contable_actual_id'),
        ]);
    }
}
