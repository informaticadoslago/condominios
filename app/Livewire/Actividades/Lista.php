<?php

namespace App\Livewire\Actividades;

use App\Livewire\ListaComponent;
use App\Models\Actividad;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{
    public function mount()
    {
        $this->sort      = 'nombre';
        $this->direction = 'asc';
    }

    #[On('actividad-guardada')]
    public function refrescar()
    {
        // el evento fuerza el re-render de la lista
    }

    protected function columnasOrdenables(): ?array
    {
        return ['nombre'];
    }

    public function confirmarEliminar($id)
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Eliminar actividad?'),
            'text'               => __('Esta acción no se puede deshacer.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#d33',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, eliminar'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarEliminarActividad',
            'cancelCallback'     => 'eliminarActividadCancelado',
            'id'                 => $id,
        ]);
    }

    #[On('ejecutarEliminarActividad')]
    public function ejecutarEliminar($id)
    {
        $actividad = Actividad::where('comunidad_id', session('comunidad_actual_id'))->find($id);
        if ($actividad) {
            $actividad->delete();
            $this->dispatch('toast-success', ['title' => __('Actividad eliminada')]);
        }
    }

    #[On('eliminarActividadCancelado')]
    public function eliminarCancelado($id = null)
    {
        // el usuario canceló; no hacemos nada
    }

    public function render()
    {
        $search = trim($this->search ?? '');

        $items = Actividad::where('comunidad_id', session('comunidad_actual_id'))
            ->when($search, function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%");
            })
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->lineasXPagina);

        return view('livewire.actividades.lista', compact('items'));
    }
}
