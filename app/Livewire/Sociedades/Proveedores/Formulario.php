<?php

namespace App\Livewire\Sociedades\Proveedores;

use App\Livewire\Sociedades\Forms\ProveedorForm;
use App\Models\Pais;
use App\Models\Proveedor;
use App\Models\TipoDocumentoIdentificativo;
use App\Models\TipoGenero;
use App\Models\TipoProveedorSociedad;
use Livewire\Attributes\On;
use Livewire\Component;

class Formulario extends Component
{
    public bool $abrir = false;

    public ProveedorForm $formulario;

    public $paises;
    public $generos;
    public $tiposProveedor;

    public function mount()
    {
        $this->paises         = Pais::activo()->ordenGrupo()->get();
        $this->generos        = TipoGenero::query()->orderBy('nombre')->get();
        $this->tiposProveedor = TipoProveedorSociedad::activo()->orderBy('cuenta_gasto')->get();

        $this->formulario->tipo_documento_identificativos = TipoDocumentoIdentificativo::all();
        $this->formulario->resetForm();
    }

    #[On('abrir-crear-proveedor')]
    public function crear()
    {
        $this->formulario->proveedor = new Proveedor();
        $this->formulario->resetForm();
        $this->formulario->sociedad_id = session('sociedad_actual_id');
        $this->abrir = true;
    }

    #[On('proveedor-editar')]
    public function editar($id)
    {
        $proveedor = Proveedor::with('persona')->find($id);
        if (! $proveedor || $proveedor->persona->sociedad_id != session('sociedad_actual_id')) {
            return;
        }

        $this->formulario->proveedor = $proveedor;
        $this->formulario->setProveedor();
        $this->abrir = true;
    }

    public function comprobarDocumento()
    {
        $this->formulario->sociedad_id = session('sociedad_actual_id');
        $this->formulario->comprobarDocumento();
    }

    public function cambiarDocumento()
    {
        $this->formulario->cambiarDocumento();
    }

    public function guardar()
    {
        // Nunca confiar en lo que traiga ya el formulario: se fuerza aquí, justo
        // antes de validar/guardar, a la sociedad real de la sesión.
        $this->formulario->sociedad_id = session('sociedad_actual_id');

        $validated = $this->formulario->validate();

        if ($this->formulario->proveedor?->exists) {
            $this->formulario->update($validated);
            $this->dispatch('toast-success', ['title' => __('Proveedor modificado')]);
        } else {
            $this->formulario->store($validated);
            $this->dispatch('toast-success', ['title' => __('Proveedor creado')]);
        }

        $this->dispatch('proveedor-guardado');
        $this->cerrar(avisar: false);
    }

    /**
     * @param  bool  $avisar  al cerrar SIN guardar se avisa, por si quien abrió el modal
     *                        está esperando el alta.
     */
    public function cerrar(bool $avisar = true)
    {
        $this->abrir = false;

        if ($avisar) {
            $this->dispatch('alta-proveedor-cancelada');
        }
    }

    public function render()
    {
        return view('livewire.sociedades.proveedores.formulario');
    }
}
