<?php

namespace App\Livewire\Proveedores;

use App\Models\Proveedor;
use App\Models\TipoDocumento;
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
        if (! $proveedor || $proveedor->persona->comunidad_id != session('comunidad_actual_id')) {
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
        $facturas = $this->proveedor
            ? $this->proveedor->documentos()
                ->where('tipo_documento_id', TipoDocumento::FACTURA)
                ->orderByDesc('fechaalta')
                ->get()
            : collect();

        return view('livewire.proveedores.ver', compact('facturas'));
    }
}
