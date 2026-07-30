<?php
namespace App\Livewire\Maestros\Paises;

use App\Livewire\ListaComponent;
use App\Livewire\Traits\ConBajaPorEstado;
use App\Models\Pais;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{
    use ConBajaPorEstado;

    protected function modeloBaja(): string
    {
        return Pais::class;
    }

    public function mount()
    {
        $this->sort      = 'nombre';
        $this->direction = 'asc';
    }

    #[On('pais-guardado')]
    public function refrescar()
    {
        // el evento fuerza el re-render de la lista
    }

    public function render()
    {
        $search = trim($this->search ?? '');

        $items = Pais::with('estado')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('nombre', 'like', "%{$search}%")
                        ->orWhere('codigo1', 'like', "%{$search}%")
                        ->orWhere('codigo2', 'like', "%{$search}%");
                });
            })
            ->orderBy($this->sort, $this->direction)
            ->orderBy('id')
            ->paginate($this->lineasXPagina);

        return view('livewire.maestros.paises.lista', compact('items'));
    }
}
