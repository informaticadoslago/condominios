<?php

namespace App\Livewire\Comunidades;

use App\Livewire\Forms\ComunidadForm;
use App\Models\Comunidad;
use Livewire\Attributes\On;
use Livewire\Component;

class Formulario extends Component
{
    public bool $abrir = false;

    public ComunidadForm $formulario;

    public function mount()
    {
        $this->formulario->resetForm();
    }

    #[On('abrir-crear-comunidad')]
    public function crear()
    {
        $this->formulario->comunidad = new Comunidad();
        $this->formulario->resetForm();
        $this->abrir = true;
    }

    #[On('comunidad-editar')]
    public function editar($id)
    {
        $comunidad = Comunidad::with('persona')->find($id);
        if (! $comunidad) {
            return;
        }

        $this->formulario->comunidad = $comunidad;
        $this->formulario->setComunidad();
        $this->abrir = true;
    }

    public function guardar()
    {
        $validated = $this->formulario->validate();

        if ($this->formulario->comunidad?->exists) {
            $this->formulario->update($validated);
            $this->dispatch('toast-success', ['title' => __('Comunidad modificada')]);
        } else {
            $this->formulario->store($validated);
            $this->dispatch('toast-success', ['title' => __('Comunidad creada')]);
        }

        $this->dispatch('comunidad-guardada');
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.comunidades.formulario');
    }
}
