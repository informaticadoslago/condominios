<?php

namespace App\Livewire\EjerciciosContables;

use App\Livewire\ListaComponent;
use App\Models\EjercicioContable;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{
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

        $items = EjercicioContable::where('comunidad_id', session('comunidad_actual_id'))
            ->when($search, function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%");
            })
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->lineasXPagina);

        return view('livewire.ejercicios-contables.lista', compact('items'));
    }
}
