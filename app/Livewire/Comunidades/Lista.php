<?php

namespace App\Livewire\Comunidades;

use App\Livewire\ListaComponent;
use App\Livewire\Traits\ConBajaPorEstado;
use App\Models\Comunidad;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{
    use ConBajaPorEstado;

    protected function modeloBaja(): string
    {
        return Comunidad::class;
    }

    public function mount()
    {
        $this->sort      = 'id';
        $this->direction = 'desc';
    }

    #[On('comunidad-guardada')]
    public function refrescar()
    {
        // el evento fuerza el re-render de la lista
    }

    public function render()
    {
        $search = trim($this->search ?? '');

        $items = Comunidad::with('estado')
            ->when($search, function ($q) use ($search) {
                $q->whereHas('persona', fn ($p) => $p
                    ->where('razon_social', 'like', "%{$search}%")
                    ->orWhere('documento_identificativo', 'like', "%{$search}%"));
            })
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->lineasXPagina);

        return view('livewire.comunidades.lista', compact('items'));
    }
}
