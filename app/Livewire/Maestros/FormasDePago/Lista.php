<?php
namespace App\Livewire\Maestros\FormasDePago;

use App\Livewire\ListaComponent;
use App\Livewire\Traits\ConBajaPorEstado;
use App\Models\FormaDePago;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{
    use ConBajaPorEstado;

    protected function modeloBaja(): string
    {
        return FormaDePago::class;
    }

    public function mount()
    {
        $this->sort      = 'descripcion';
        $this->direction = 'asc';
    }

    #[On('forma-de-pago-guardada')]
    public function refrescar()
    {
        // el evento fuerza el re-render de la lista
    }

    public function render()
    {
        $search = trim($this->search ?? '');

        $items = FormaDePago::with('estado')
            ->when($search, fn ($q) => $q->where('descripcion', 'like', "%{$search}%"))
            ->orderBy($this->sort, $this->direction)
            ->orderBy('id')
            ->paginate($this->lineasXPagina);

        return view('livewire.maestros.formas-de-pago.lista', compact('items'));
    }
}
