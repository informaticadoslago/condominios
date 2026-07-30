<?php
namespace App\Livewire\AdministracionSistema\Permisos;

use Livewire\Attributes\On;
use Livewire\Component;
use Spatie\Permission\Models\Permission;

class Crear extends Component
{
    public bool $abrirCrear    = false;
    public bool $activoGuardar = true;

    public string $nombre;

    public function rules()
    {
        $rules = [
            'nombre' => ['required', 'string', 'max:255', 'unique:permissions,name'],
        ];
        return $rules;
    }

    public function messages()
    {
        $mensajes = [
            'unique' => __('Ya existe un :modelo con ese nombre.', ['modelo' => __('permiso')]),
        ];

        return $mensajes;

    }

    public function render()
    {
        return view('livewire.administracion-sistema.permisos.crear');
    }

    #[On('abrir-crear')]
    public function abrirModal()
    {
        abort_unless(auth()->user()->can('permiso-create'), 403);

        $this->abrirCrear = true;
    }

    public function guardar()
    {
        abort_unless(auth()->user()->can('permiso-create'), 403);

        $validated = $this->validate();

        $permiso = Permission::create([
            'name'       => $validated['nombre'],
            'guard_name' => 'web',
        ]);

        if ($permiso) {            
            $this->dispatch('toast-success', [
                'title' => __('Permiso ha sido creado'),
            ]);
        } else {
            $this->dispatch('toast-error', [
                'title' => __('Error: el permiso NO ha sido creado'),
            ]);
        }
        $this->dispatch('permisos-renderizado');
        $this->close();
    }

    public function close()
    {
        $this->reset();
        $this->abrirCrear = false;
    }

}
