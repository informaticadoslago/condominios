<?php

namespace App\Livewire\GruposDeReparto;

use App\Livewire\ListaComponent;
use App\Models\GrupoDeReparto;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{
    public function mount()
    {
        $this->sort      = 'nombre';
        $this->direction = 'asc';
    }

    #[On('grupo-de-reparto-guardado')]
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
            'title'              => __('¿Eliminar grupo de reparto?'),
            'text'               => __('Esta acción no se puede deshacer.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#d33',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, eliminar'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarEliminarGrupoDeReparto',
            'cancelCallback'     => 'eliminarGrupoDeRepartoCancelado',
            'id'                 => $id,
        ]);
    }

    #[On('ejecutarEliminarGrupoDeReparto')]
    public function ejecutarEliminar($id)
    {
        $grupo = GrupoDeReparto::where('comunidad_id', session('comunidad_actual_id'))->find($id);
        if ($grupo) {
            $grupo->inmuebles()->detach();
            $grupo->delete();
            $this->dispatch('toast-success', ['title' => __('Grupo de reparto eliminado')]);
        }
    }

    #[On('eliminarGrupoDeRepartoCancelado')]
    public function eliminarCancelado($id = null)
    {
        // el usuario canceló; no hacemos nada
    }

    public function render()
    {
        $search = trim($this->search ?? '');

        $items = GrupoDeReparto::withCount('inmuebles')
            ->where('comunidad_id', session('comunidad_actual_id'))
            ->when($search, function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%");
            })
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->lineasXPagina);

        return view('livewire.grupos-de-reparto.lista', compact('items'));
    }
}
