<?php

namespace App\Livewire\ProyectosContables;

use App\Livewire\ListaComponent;
use App\Livewire\Traits\ConEmpresaContableActiva;
use App\Models\ProyectoContable;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{
    use ConEmpresaContableActiva;

    public function mount()
    {
        $this->sort      = 'nombre';
        $this->direction = 'asc';
    }

    #[On('proyecto-contable-guardado')]
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
            'title'              => __('¿Eliminar proyecto?'),
            'text'               => __('Esta acción no se puede deshacer.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#d33',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, eliminar'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarEliminarProyectoContable',
            'cancelCallback'     => 'eliminarProyectoContableCancelado',
            'id'                 => $id,
        ]);
    }

    #[On('ejecutarEliminarProyectoContable')]
    public function ejecutarEliminar($id)
    {
        $empresaContableId = $this->empresaContableActual()?->id ?? 0;

        $proyecto = ProyectoContable::where('empresa_contable_id', $empresaContableId)->find($id);
        if ($proyecto) {
            $proyecto->delete();
            $this->dispatch('toast-success', ['title' => __('Proyecto eliminado')]);
        }
    }

    #[On('eliminarProyectoContableCancelado')]
    public function eliminarCancelado($id = null)
    {
        // el usuario canceló; no hacemos nada
    }

    public function render()
    {
        $search = trim($this->search ?? '');
        $empresaContableId = $this->empresaContableActual()?->id ?? 0;

        $items = ProyectoContable::where('empresa_contable_id', $empresaContableId)
            ->when($search, function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%");
            })
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->lineasXPagina);

        return view('livewire.proyectos-contables.lista', compact('items'));
    }
}
