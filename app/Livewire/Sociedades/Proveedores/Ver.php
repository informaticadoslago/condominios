<?php

namespace App\Livewire\Sociedades\Proveedores;

use App\Models\Proveedor;
use Livewire\Attributes\On;
use Livewire\Component;

class Ver extends Component
{
    public bool $abrir = false;

    public ?Proveedor $proveedor = null;

    #[On('proveedor-ver')]
    public function mostrar($id)
    {
        $proveedor = Proveedor::with('persona')->find($id);
        if (! $proveedor || $proveedor->persona->sociedad_id != session('sociedad_actual_id')) {
            return;
        }

        $this->proveedor = $proveedor;
        $this->abrir     = true;
    }

    public function editar()
    {
        $this->abrir = false;
        $this->dispatch('proveedor-editar', id: $this->proveedor->id);
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.sociedades.proveedores.ver');
    }
}
