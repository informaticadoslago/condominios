<?php
namespace App\Livewire\AdministracionSistema\Permisos;

use Livewire\Attributes\On;
use App\Livewire\ListaComponent;
use Spatie\Permission\Models\Permission;

class Lista extends ListaComponent
{

    public function mount()
    {
        $this->sort      = 'name';
        $this->direction = 'asc';
    }

    // public function refreshList()
    // {
    //     //
    // }

    #[On('permisos-renderizado')]
    public function render()
    {
        $permisos = Permission::where('name', 'like', '%' . $this->search . '%')
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->lineasXPagina);
        return view('livewire.administracion-sistema.permisos.lista', compact('permisos'));
    }
}
