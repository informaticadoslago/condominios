<?php

namespace App\Livewire\EjerciciosContables;

use App\Livewire\ListaComponent;
use App\Livewire\Traits\ConEmpresaContableActiva;
use App\Models\EjercicioContable;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{
    use ConEmpresaContableActiva;

    public function mount()
    {
        $this->sort      = 'fecha_inicio';
        $this->direction = 'desc';
    }

    #[On('ejercicio-contable-guardado')]
    public function refrescar()
    {
        // el evento fuerza el re-render de la lista
    }

    protected function columnasOrdenables(): ?array
    {
        return ['nombre', 'fecha_inicio', 'fecha_fin'];
    }

    public function render()
    {
        $search = trim($this->search ?? '');
        $empresaContableId = $this->empresaContableActual()?->id ?? 0;

        $items = EjercicioContable::where('empresa_contable_id', $empresaContableId)
            ->when($search, function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%");
            })
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->lineasXPagina);

        return view('livewire.ejercicios-contables.lista', compact('items'));
    }
}
