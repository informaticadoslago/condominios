<?php

namespace App\Livewire\Inmuebles;

use App\Models\Inmueble;
use Livewire\Component;

/**
 * Página que aloja el wizard de alta/edición de inmueble (App\Livewire\Inmuebles\Crear\CrearInmueble).
 */
class Formulario extends Component
{
    public ?int $inmuebleId = null;

    public function mount(?Inmueble $inmueble = null)
    {
        $this->inmuebleId = $inmueble?->id;
    }

    public function render()
    {
        return view('livewire.inmuebles.formulario');
    }
}
