<?php

namespace App\Livewire\Asignaturas;

use App\Livewire\ListaComponent;
use App\Livewire\Traits\ConFiltroEstado;
use App\Livewire\Traits\ConHistorialEstadoModal;
use App\Models\Asignatura;
use App\Models\Estado;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{
    use ConFiltroEstado;
    use ConHistorialEstadoModal;

    public function mount()
    {
        $this->sort      = 'nombre';
        $this->direction = 'asc';
    }

    #[On('asignatura-guardado')]
    public function refrescar()
    {
        // el evento fuerza el re-render de la lista
    }

    protected function modeloEstado(): string
    {
        return Estado::class;
    }

    protected function modeloHistorial(): string
    {
        return Asignatura::class;
    }

    public function definicionesFiltro(): array
    {
        return [
            $this->filtroEstado(),
        ];
    }

    public function columnasDisponibles(): array
    {
        return [
            'nombre' => __('Nombre'),
            'estado' => __('Estado'),
        ];
    }

    public function confirmarBaja($id)
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Dar de baja la asignatura?'),
            'text'               => __('Se marcará como inactiva.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#d33',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, dar de baja'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarBaja',
            'cancelCallback'     => 'bajaCancelada',
            'id'                 => $id,
        ]);
    }

    #[On('ejecutarBaja')]
    public function ejecutarBaja($id)
    {
        $asignatura = Asignatura::where('horario_id', session('horario_actual_id'))->find($id);

        if ($asignatura) {
            $asignatura->update(['estado_id' => Asignatura::ESTADO_BAJA]);
            $this->dispatch('toast-success', ['title' => __('Asignatura dada de baja')]);
        }
    }

    public function confirmarReactivar($id)
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Reactivar la asignatura?'),
            'text'               => __('Se marcará como activa.'),
            'icon'               => 'question',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#3085d6',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, reactivar'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarReactivar',
            'cancelCallback'     => 'bajaCancelada',
            'id'                 => $id,
        ]);
    }

    #[On('ejecutarReactivar')]
    public function ejecutarReactivar($id)
    {
        $asignatura = Asignatura::where('horario_id', session('horario_actual_id'))->find($id);

        if ($asignatura) {
            $asignatura->update(['estado_id' => Asignatura::ESTADO_ACTIVO]);
            $this->dispatch('toast-success', ['title' => __('Asignatura reactivada')]);
        }
    }

    #[On('bajaCancelada')]
    public function bajaCancelada($id = null)
    {
        // el usuario canceló; no hacemos nada
    }

    public function render()
    {
        $search = trim($this->search ?? '');

        $items = $this->aplicarFiltros(
            Asignatura::with('estado')
                ->withCount('historialEstados')
                ->where('horario_id', session('horario_actual_id'))
        )
            ->when($search, fn ($q) => $q->where('nombre', 'like', "%{$search}%"))
            ->orderBy($this->sort, $this->direction)
            ->orderBy('id')
            ->paginate($this->lineasXPagina);

        return view('livewire.asignaturas.lista', compact('items'));
    }
}
