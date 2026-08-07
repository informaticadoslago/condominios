<?php

namespace App\Livewire\Proveedores;

use App\Livewire\Forms\ProveedorForm;
use App\Models\Pais;
use App\Models\Proveedor;
use App\Models\TipoDocumentoIdentificativo;
use App\Models\TipoGenero;
use Livewire\Attributes\On;
use Livewire\Component;

class Formulario extends Component
{
    public bool $abrir = false;

    public ProveedorForm $formulario;

    public $paises;
    public $generos;

    public function mount()
    {
        $this->paises  = Pais::activo()->ordenGrupo()->get();
        $this->generos = TipoGenero::query()->orderBy('nombre')->get();

        $this->formulario->tipo_documento_identificativos = TipoDocumentoIdentificativo::all();
        $this->formulario->resetForm();
    }

    #[On('abrir-crear-proveedor')]
    public function crear()
    {
        $this->formulario->proveedor = new Proveedor();
        $this->formulario->resetForm();
        $this->formulario->comunidad_id = session('comunidad_actual_id');
        $this->abrir = true;
    }

    /**
     * Alta con el documento ya sabido: viene de la captura de facturas, donde se acaba de
     * comprobar que es correcto y de nadie. Se salta el paso 1 y se abre directamente en
     * la razón social (o el nombre, si el documento es de persona física).
     */
    #[On('abrir-crear-proveedor-con-documento')]
    public function crearConDocumento($documento, $paisId, $tipoId)
    {
        $this->crear();

        $this->formulario->documento_identificativo = $documento;
        $this->formulario->documento_pais_id        = $paisId;
        $this->formulario->tipo_documento_id        = $tipoId;

        $this->formulario->comprobarDocumento();
    }

    #[On('proveedor-editar')]
    public function editar($id)
    {
        $proveedor = Proveedor::with('persona')->find($id);
        if (! $proveedor || $proveedor->persona->comunidad_id != session('comunidad_actual_id')) {
            return;
        }

        $this->formulario->proveedor = $proveedor;
        $this->formulario->setProveedor();
        $this->abrir = true;
    }

    public function comprobarDocumento()
    {
        $this->formulario->comunidad_id = session('comunidad_actual_id');
        $this->formulario->comprobarDocumento();
    }

    public function cambiarDocumento()
    {
        $this->formulario->cambiarDocumento();
    }

    public function guardar()
    {
        // Nunca confiar en lo que traiga ya el formulario: se fuerza aquí, justo
        // antes de validar/guardar, a la comunidad real de la sesión.
        $this->formulario->comunidad_id = session('comunidad_actual_id');

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

    public function borrarDocumento($facturaProveedorId)
    {
        $factura = $this->formulario->proveedor->facturas()->find($facturaProveedorId);
        if (! $factura) {
            return;
        }

        $factura->documento->delete(); // borra el fichero físico (Documento::booted) y, en cascada, esta fila
        $this->dispatch('toast-success', ['title' => __('Documento borrado')]);
    }

    /**
     * @param  bool  $avisar  al cerrar SIN guardar se avisa, porque quien abrió el modal
     *                        puede estar esperando el alta (la captura de facturas).
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
        $facturas = $this->formulario->proveedor?->exists
            ? $this->formulario->proveedor->facturas()
                ->with('documento')
                ->orderByDesc('created_at')
                ->get()
            : collect();

        return view('livewire.proveedores.formulario', compact('facturas'));
    }
}
