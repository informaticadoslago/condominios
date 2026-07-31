<?php
namespace App\Livewire\AdministracionSistema\Personas;

use App\Livewire\ListaComponent;
use App\Models\Persona;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{

    public function mount()
    {
        $this->sort      = 'nombre';
        $this->direction = 'asc';
    }

    // El único orden que ofrece el blade. Protege contra preferencias guardadas de cuando
    // la columna se llamaba 'nombre_completo' (era un alias SQL real; hoy es un accessor).
    protected function columnasOrdenables(): ?array
    {
        return ['nombre'];
    }

    // El modal de roles (usuario) puede cambiar el nombre de la persona.
    #[On('usuarios-renderizado')]
    public function refrescar()
    {
        // Basta con re-renderizar la lista.
    }

    // public function editar($direccionId)
    // {
    //     $this->dispatch('editar-direccion')->to(Editar::class);
    // }

    public function render()
    {
        $search   = trim($this->search); // lo que viene del input
        $personas = $this->aplicarOrden(
            Persona::query()
                ->with(['usuario'])
                ->buscarNombreCompleto($search)
        )->paginate($this->lineasXPagina);
        return view('livewire.administracion-sistema.personas.lista', compact('personas'));
    }
}
