<?php
namespace App\Livewire\AdministracionSistema\Roles;

use App\Livewire\ListaComponent;
use Livewire\Attributes\On;
use Spatie\Permission\Models\Role;

class Lista extends ListaComponent
{
    public function mount()
    {
        $this->sort      = 'name';
        $this->direction = 'asc';
    }

    #[On('roles-renderizado')]
    public function render()
    {
        $roles = Role::where('name', 'like', '%' . $this->search . '%')
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->lineasXPagina);

        return view('livewire.administracion-sistema.roles.lista', compact('roles'));
    }
}
