<?php
namespace App\Livewire\AdministracionSistema\Personas;

use App\Models\Persona;
use Livewire\Component;
use Livewire\Attributes\On;
use App\Livewire\Forms\PersonaForm;
use App\Livewire\Traits\WithGenero;
use App\Livewire\Traits\WithDireccion;
use App\Models\TipoDocumentoIdentificativo;

class Editar extends Component
{
    use WithGenero;
    use WithDireccion;

    public bool $abrirModificarPersona = false;
    public PersonaForm $formulario;

    public function inicializa()
    {
        $this->setPaises();
        $this->setGeneros();
        $this->formulario->refrescarTiposDocumento();
    }

    public function mount()
    {
        $this->inicializa();
    }

    public function render()
    {
        return view('livewire.administracion-sistema.personas.editar');
    }

    #[On('abrir-modificar-persona')]
    public function abrirModal($personaId)
    {
        $persona = Persona::find($personaId); // Devuelve null si no existe
        if (! $persona) { // Esto es true cuando $persona es null
            session()->flash('error', 'La persona no existe o ha sido eliminada.');
            return; // Sale del método sin continuar
        }

        // Solo llega aquí si $persona NO es null
        $this->formulario->persona = $persona;
        $this->formulario->setPersona();
        // Ajustar los tipos de documento al grupo del país de la persona cargada.
        $this->formulario->refrescarTiposDocumento();
        $this->abrirModificarPersona = true;
    }

    public function guardar()
    {
        $validated = $this->formulario->validate();
        $persona   = $this->formulario->update($validated);
        $this->dispatch('persona-actualizada', $persona->id);
        $this->cerrar();

    }

    public function cerrar()
    {
        $this->abrirModificarPersona = false;
        $this->formulario->resetForm();
    }

}
