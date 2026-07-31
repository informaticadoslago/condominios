<?php

namespace App\Livewire\Propietarios;

use App\Livewire\Forms\PropietarioForm;
use App\Models\Pais;
use App\Models\Propietario;
use App\Models\TipoDocumentoIdentificativo;
use App\Models\TipoGenero;
use Livewire\Attributes\On;
use Livewire\Component;

class Formulario extends Component
{
    public bool $abrir = false;

    public PropietarioForm $formulario;

    public $paises;
    public $generos;

    public function mount()
    {
        $this->paises  = Pais::activo()->ordenGrupo()->get();
        $this->generos = TipoGenero::query()->orderBy('nombre')->get();

        $this->formulario->tipo_documento_identificativos = TipoDocumentoIdentificativo::all();
        $this->formulario->resetForm();
    }

    #[On('abrir-crear-propietario')]
    public function crear()
    {
        $this->formulario->propietario = new Propietario();
        $this->formulario->resetForm();
        $this->abrir = true;
    }

    #[On('propietario-editar')]
    public function editar($id)
    {
        $propietario = Propietario::with('persona')->find($id);
        if (! $propietario) {
            return;
        }

        $this->formulario->propietario = $propietario;
        $this->formulario->setPropietario();
        $this->abrir = true;
    }

    public function comprobarDocumento()
    {
        $this->formulario->comprobarDocumento();
    }

    public function cambiarDocumento()
    {
        $this->formulario->cambiarDocumento();
    }

    public function guardar()
    {
        $validated = $this->formulario->validate();

        if ($this->formulario->propietario?->exists) {
            $this->formulario->update($validated);
            $this->dispatch('toast-success', ['title' => __('Propietario modificado')]);
        } else {
            $this->formulario->store($validated);
            $this->dispatch('toast-success', ['title' => __('Propietario creado')]);
        }

        $this->dispatch('propietario-guardado');
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.propietarios.formulario');
    }
}
