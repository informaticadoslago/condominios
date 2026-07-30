<?php
namespace App\Livewire\AdministracionSistema\Personas;

use App\Models\Pais;
use App\Models\Persona;
use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\TipoDireccion;
use App\Livewire\Forms\PersonaForm;
use App\Livewire\Traits\WithGenero;
use App\Livewire\Traits\WithDireccion;
use App\Models\TipoDocumentoIdentificativo;

class Crear extends Component
{
    use WithDireccion;
    use WithGenero;
    //use HasPersonaForm;

    public bool $abrirCrearPersona = false;
    public PersonaForm $formulario;
    // public DireccionForm $direccionForm;
    // public $tiposDirecciones;

    public $incluirAdministrador = true;

    private $inicializado = false;

    #[Computed]
    public function tipoDirecciones()
    {
        return TipoDireccion::orderBy('nombre')->get();
    }

    public function inicializa()
    {
        if ($this->inicializado) {
            return;
        }
        $this->formulario->fecha_alta                     = date('Y-m-d');
        $this->formulario->persona                        = new Persona();
        $this->tipos_direccion                            = $this->tipoDirecciones();
        $this->formulario->refrescarTiposDocumento();
        $this->formulario->tipo_documento_id              = TipoDocumentoIdentificativo::DOCUMENTO_NIF;
        $this->setPaises();
        if ($this->paises) {
            $this->formulario->documento_pais_id = Pais::porDefecto();
            $this->setProvincias();
        }
        $this->setGeneros();
        $this->inicializado = true;
    }
    public function mount()
    {
//        $this->incluirAdministrador       = $this->usuarioEsSuperAdmin();
        $this->inicializa();
    }

    #[On('abrir-crear-persona')]
    public function abrir()
    {
        $this->inicializa();
        $this->formulario->resetForm(); // Limpia el formulario
        $this->abrirCrearPersona = true;
    }

    public function guardar()
    {
        $validated = $this->formulario->validate();
        $persona   = $this->formulario->store($validated);
        $this->dispatch('persona-creada', $persona->id);
        $this->cerrar();

    }

    public function render()
    {
        return view('livewire.administracion-sistema.personas.crear')->with('persona', $this->formulario->persona);
    }

    public function cerrar()
    {
        $this->abrirCrearPersona = false;
        $this->formulario->resetForm();
    }
}
