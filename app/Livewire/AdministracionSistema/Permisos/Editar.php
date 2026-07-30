<?php
namespace App\Livewire\AdministracionSistema\Permisos;

use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;
use Spatie\Permission\Models\Permission;

class Editar extends Component
{
    public bool $abrirEditar   = false;
    public bool $activoGuardar = true;

    public Permission $permiso;

    public string $nombre;

    public function rules()
    {
        $rules['nombre'] = ['required', 'string', 'max:255', Rule::unique('permissions', 'name')->ignore($this->permiso->id)];
        return $rules;
    }

    public function messages()
    {
        $mensajes = [
            'unique' => __('Ya existe un :modelo con ese nombre.', ['modelo' => __('permiso')]),
        ];

        return $mensajes;

    }

    #[On('permisoeditar')]
    public function editar($permiso_id)
    {
        abort_unless(auth()->user()->can('permiso-edit'), 403);

        $permiso = Permission::find($permiso_id);
        if ($permiso) {
            $this->permiso     = $permiso;
            $this->nombre      = $permiso->name;
            $this->abrirEditar = true;
        } else {
            dd("no existe permiso");
        }
    }

    public function render()
    {
        return view('livewire.administracion-sistema.permisos.editar');
    }

    public function guardar()
    {
        abort_unless(auth()->user()->can('permiso-edit'), 403);

        $validated = $this->validate();
        $permiso   = $this->permiso;
        $permiso->update([
            'name' => $validated['nombre'],
        ]);
        if ($permiso) {
            $this->dispatch('renderiza-permisos');
            $this->dispatch('toast-success', [
                'title' => __('Permiso ha sido modificado'),
            ]);
        } else {
            $this->dispatch('toast-error', [
                'title' => __('Error: el permiso NO ha sido modificado'),
            ]);
        }
        $this->dispatch('permisos-renderizado');
        $this->close();
    }

    public function close()
    {
        $this->reset();
        $this->abrirEditar = false;
    }

}
