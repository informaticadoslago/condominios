<?php

namespace App\Livewire\EmpresasContables;

use App\Livewire\ListaComponent;
use App\Livewire\Traits\ConFichaInicio;
use App\Models\AccesoDirecto;
use App\Models\EmpresaContable;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{
    use ConFichaInicio;

    public function mount()
    {
        $this->sort      = 'razon_social';
        $this->direction = 'asc';
    }

    /**
     * Solo son fijables las empresas contables en las que este usuario puede entrar:
     * la ficha del inicio es un atajo del menú lateral, no una puerta nueva.
     */
    protected function fichaInicioPara($id): ?array
    {
        $empresa = auth()->user()->empresasContablesAccesibles()->firstWhere('id', (int) $id);

        if (! $empresa) {
            return null;
        }

        return [
            'tipo'   => AccesoDirecto::TIPO_EMPRESA_CONTABLE,
            'nombre' => $empresa->razon_social,
            'url'    => route('empresa-contable.entrar', $empresa, false),
            'icono'  => 'fa-solid fa-calculator',
        ];
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
            'items'                   => $items,
            'empresaContableActualId' => session('empresa_contable_actual_id'),
            'idsAccesibles'           => auth()->user()->empresasContablesAccesibles()->pluck('id')->all(),
        ]);
    }
}
