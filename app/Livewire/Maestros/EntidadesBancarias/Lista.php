<?php
namespace App\Livewire\Maestros\EntidadesBancarias;

use App\Livewire\ListaComponent;
use App\Livewire\Traits\ConBajaPorEstado;
use App\Models\EntidadBancaria;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{
    use ConBajaPorEstado;

    protected function modeloBaja(): string
    {
        return EntidadBancaria::class;
    }

    public function mount()
    {
        $this->sort      = 'descripcion';
        $this->direction = 'asc';
    }

    #[On('entidad-bancaria-guardada')]
    public function refrescar()
    {
        // el evento fuerza el re-render de la lista
    }

    public function render()
    {
        $search = trim($this->search ?? '');

        $items = EntidadBancaria::with('estado')
            ->when($search, function ($q) use ($search) {
                $q->where('descripcion', 'like', "%{$search}%")
                    ->orWhere('codigo', 'like', "%{$search}%")
                    ->orWhere('bic', 'like', "%{$search}%");
            })
            ->orderBy($this->sort, $this->direction)
            ->orderBy('id')
            ->paginate($this->lineasXPagina);

        return view('livewire.maestros.entidades-bancarias.lista', compact('items'));
    }
}
