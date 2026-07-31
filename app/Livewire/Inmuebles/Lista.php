<?php

namespace App\Livewire\Inmuebles;

use App\Livewire\ListaComponent;
use App\Models\Inmueble;
use App\Models\Titularidad;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{
    public function mount()
    {
        $this->sort      = 'id';
        $this->direction = 'desc';
    }

    public function confirmarEliminar($id)
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Eliminar inmueble?'),
            'text'               => __('Esta acción no se puede deshacer.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#d33',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, eliminar'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarEliminarInmueble',
            'cancelCallback'     => 'eliminarInmuebleCancelado',
            'id'                 => $id,
        ]);
    }

    #[On('ejecutarEliminarInmueble')]
    public function ejecutarEliminar($id)
    {
        $inmueble = Inmueble::find($id);
        if ($inmueble) {
            // Las FK son restrict: hay que soltar las relaciones antes de borrar. Ojo:
            // propietarios() solo ve las titularidades VIGENTES, así que para no dejar
            // huérfanas las cerradas (histórico), se borran todas por inmueble_id directo.
            Titularidad::where('inmueble_id', $inmueble->id)->delete();
            $inmueble->gruposDeReparto()->detach();
            $inmueble->delete();
            $this->dispatch('toast-success', ['title' => __('Inmueble eliminado')]);
        }
    }

    #[On('eliminarInmuebleCancelado')]
    public function eliminarCancelado($id = null)
    {
        // el usuario canceló; no hacemos nada
    }

    public function render()
    {
        $search = trim($this->search ?? '');

        $items = Inmueble::with(['comunidad.persona', 'ocupacion', 'tipoInmueble', 'propietarios.persona'])
            ->when($search, function ($q) use ($search) {
                $q->where('puerta', 'like', "%{$search}%")
                    ->orWhere('referencia_catastral', 'like', "%{$search}%")
                    ->orWhereHas('comunidad.persona', fn ($p) => $p->where('razon_social', 'like', "%{$search}%"));
            })
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->lineasXPagina);

        return view('livewire.inmuebles.lista', compact('items'));
    }
}
