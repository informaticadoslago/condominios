<?php
namespace App\Livewire\AdministracionSistema\Empresa;

use App\Livewire\Forms\EmpresaForm;
use App\Livewire\Traits\WithDireccion;
use App\Livewire\Traits\WithGenero;
use App\Models\Empresa;
use App\Models\Pais;
use App\Models\TipoDocumentoIdentificativo;
use Livewire\Component;

class Editar extends Component
{
    use WithDireccion;
    use WithGenero;

    public EmpresaForm $formulario;

    public function mount()
    {
        $this->formulario->tipo_documento_identificativos = TipoDocumentoIdentificativo::all();
        $this->setPaises();
        $this->setGeneros();

        // El sistema no es multiempresa: cargamos la única empresa si existe.
        $empresa = Empresa::first();

        if ($empresa) {
            $this->formulario->empresa = $empresa;
            $this->formulario->setEmpresa();
        } else {
            // Sin empresa aún: formulario vacío listo para crear.
            $this->formulario->empresa               = new Empresa();
            $this->formulario->fecha_alta            = date('Y-m-d');
            $this->formulario->tipo_documento_id     = TipoDocumentoIdentificativo::DOCUMENTO_CIF;
            $this->formulario->es_tipo_documento_cif = true;
            $this->formulario->documento_pais_id     = Pais::porDefecto();
        }
    }

    public function guardar()
    {
        $validated = $this->formulario->validate();

        if ($this->formulario->empresa->exists) {
            $this->formulario->update($validated);
            session()->flash('mensaje', __('Datos de empresa guardados.'));
        } else {
            $this->formulario->store($validated);
            session()->flash('mensaje', __('Empresa creada.'));
        }
    }

    public function render()
    {
        return view('livewire.administracion-sistema.empresa.editar');
    }
}
