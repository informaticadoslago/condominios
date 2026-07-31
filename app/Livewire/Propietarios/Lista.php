<?php

namespace App\Livewire\Propietarios;

use App\Livewire\ListaComponent;
use App\Models\Propietario;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{
    public function mount()
    {
        $this->sort      = 'id';
        $this->direction = 'desc';
    }

    #[On('propietario-guardado')]
    public function refrescar()
    {
        // el evento fuerza el re-render de la lista
    }

    public function confirmarEliminar($id)
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Eliminar propietario?'),
            'text'               => __('Esta acción no se puede deshacer.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#d33',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, eliminar'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarEliminarPropietario',
            'cancelCallback'     => 'eliminarPropietarioCancelado',
            'id'                 => $id,
        ]);
    }

    #[On('ejecutarEliminarPropietario')]
    public function ejecutarEliminar($id)
    {
        Propietario::whereKey($id)
            ->whereHas('persona', fn ($p) => $p->where('comunidad_id', session('comunidad_actual_id')))
            ->delete();
        $this->dispatch('toast-success', ['title' => __('Propietario eliminado')]);
    }

    #[On('eliminarPropietarioCancelado')]
    public function eliminarCancelado($id = null)
    {
        // el usuario canceló; no hacemos nada
    }

    public function render()
    {
        $search = trim($this->search ?? '');

        $items = Propietario::with('persona')
            ->whereHas('persona', fn ($p) => $p->where('comunidad_id', session('comunidad_actual_id')))
            ->when($search, function ($q) use ($search) {
                $q->whereHas('persona', fn ($p) => $p
                    ->buscarNombreCompleto($search)
                    ->orWhere('documento_identificativo', 'like', "%{$search}%"));
            })
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->lineasXPagina);

        return view('livewire.propietarios.lista', compact('items'));
    }
}
