<?php

namespace App\Livewire\Maestros\Periodicidades;

use App\Livewire\ListaComponent;
use App\Livewire\Traits\ConBajaPorEstado;
use App\Models\TipoPeriodicidadPago;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{
    use ConBajaPorEstado;

    protected function modeloBaja(): string
    {
        return TipoPeriodicidadPago::class;
    }

    public function mount()
    {
        $this->sort      = 'meses';
        $this->direction = 'asc';
    }

    #[On('periodicidad-guardada')]
    public function refrescar()
    {
        // el evento fuerza el re-render de la lista
    }

    public function render()
    {
        $search = trim($this->search ?? '');

        $items = TipoPeriodicidadPago::with('estado')
            ->when($search, fn ($q) => $q->where('descripcion', 'like', "%{$search}%"))
            ->orderBy($this->sort, $this->direction)
            ->orderBy('id')
            ->paginate($this->lineasXPagina);

        return view('livewire.maestros.periodicidades.lista', compact('items'));
    }
}
