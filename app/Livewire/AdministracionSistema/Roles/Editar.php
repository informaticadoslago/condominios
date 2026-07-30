<?php

namespace App\Livewire\AdministracionSistema\Roles;

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class Editar extends Component
{
    public bool $abrirEditar = false;
    public bool $activoGuardar = true;

    public Role $rol;

    public string $nombre;
    public array $permisos_rol = [];
    public array $rolePermissions = [];

    public function rules()
    {           
        $rules = [
            'nombre' => ['required', 'string', 'max:255',],
            'permisos_rol' => 'required',
        ];        
        $rules['nombre'][]= Rule::unique('roles','name')->ignore($this->rol->id);
        return $rules;
    }
    
    public function messages()
    {
        $mensajes = [
            'unique' => __('Ya existe un :modelo con ese nombre.',['modelo'=>__('rol')]),
        ];

        return $mensajes;

    }


    #[On('roleditar')]
    public function editar($rol_id)
    {
        abort_unless(auth()->user()->can('role-edit'), 403);

        $rol = Role::with('permissions')->find($rol_id);
        if ($rol) {            
            $this->rol = $rol;                        
            $this->nombre = $rol->name;
            $this->permisos_rol = $rol->permissions()->pluck('name')->all();            
            $this->abrirEditar = true;
        } else {
            dd("no existe rol");
        }
    }

    public function render()
    {
        $permisos = Permission::orderBy('name')->get();
        return view('livewire.administracion-sistema.roles.editar', ['permisos'=>$permisos,'rolePermissions'=>$this->rolePermissions]);
    }

    public function guardar()
    {
        abort_unless(auth()->user()->can('role-edit'), 403);

        $validated = $this->validate();
        $rol = $this->rol;
        $rol->update([
            'name' => $validated['nombre'],
        ]);
        if ($rol) {            
            $rol->syncPermissions($validated['permisos_rol']);
            $this->dispatch('toast-success', [
                'title' => __('Rol ha sido modificado'),
            ]);
        } else {
            $this->dispatch('toast-error', [
                'title' => __('Error: el rol NO ha sido modificado'),
            ]);
        }
        $this->dispatch('roles-renderizado');
        $this->close();
    }

    public function close()
    {
        $this->reset();
        $this->abrirEditar = false;
    }

}
