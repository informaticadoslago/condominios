<?php
namespace App\Livewire\AdministracionSistema\Roles;

use Livewire\Attributes\On;
use App\Livewire\CrudComponent;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class Crear extends CrudComponent
{
    
    public bool $activoGuardar = true;

    public string $nombre;
    public array $permisos_rol = [];

    public function rules()
    {
        $rules = [
            'nombre'       => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permisos_rol' => 'required',
        ];
        return $rules;
    }

    public function messages()
    {
        $mensajes = [
            'unique' => __('Ya existe un :modelo con ese nombre.', ['modelo' => __('rol')]),
        ];

        return $mensajes;

    }

    public function render()
    {
        $permisos = Permission::orderBy('name')->get();
        return view('livewire.administracion-sistema.roles.crear', compact('permisos'));
    }

    #[On('abrir-crear')]
    public function abrirModal()
    {
        abort_unless(auth()->user()->can('role-create'), 403);

        $this->abrirCrear = true;
    }

    public function guardar()
    {
        abort_unless(auth()->user()->can('role-create'), 403);

        $validated = $this->validate();
        $rol       = Role::create([
            'name'       => $validated['nombre'],
            'guard_name' => 'web',
        ]);
        if ($rol) {            
            $rol->syncPermissions($validated['permisos_rol']);
            $this->dispatch('toast-success', [
                'title' => __('Rol ha sido creado'),
            ]);
        } else {
            $this->dispatch('toast-error', [
                'title' => __('Error: el rol NO ha sido creado'),
            ]);
        }
        $this->dispatch('roles-renderizado');
        $this->close();
    }

}
