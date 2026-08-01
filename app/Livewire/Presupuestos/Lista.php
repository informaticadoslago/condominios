<?php

namespace App\Livewire\Presupuestos;

use App\Livewire\ListaComponent;
use App\Models\Presupuesto;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{
    public function mount()
    {
        $this->sort      = 'anho';
        $this->direction = 'desc';
    }

    #[On('presupuesto-guardado')]
    public function refrescar()
    {
        // el evento fuerza el re-render de la lista
    }

    protected function columnasOrdenables(): ?array
    {
        return ['nombre', 'anho'];
    }

    public function confirmarEliminar($id)
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Eliminar presupuesto?'),
            'text'               => __('Esta acción no se puede deshacer.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#d33',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, eliminar'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarEliminarPresupuesto',
            'cancelCallback'     => 'eliminarPresupuestoCancelado',
            'id'                 => $id,
        ]);
    }

    #[On('ejecutarEliminarPresupuesto')]
    public function ejecutarEliminar($id)
    {
        $presupuesto = Presupuesto::where('comunidad_id', session('comunidad_actual_id'))->find($id);
        if ($presupuesto) {
            $presupuesto->conceptos()->delete();
            $presupuesto->delete();
            $this->dispatch('toast-success', ['title' => __('Presupuesto eliminado')]);
        }
    }

    #[On('eliminarPresupuestoCancelado')]
    public function eliminarCancelado($id = null)
    {
        // el usuario canceló; no hacemos nada
    }

    public function render()
    {
        $search = trim($this->search ?? '');

        $items = Presupuesto::with('estado')
            ->where('comunidad_id', session('comunidad_actual_id'))
            ->when($search, function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%");
            })
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->lineasXPagina);

        return view('livewire.presupuestos.lista', compact('items'));
    }
}
