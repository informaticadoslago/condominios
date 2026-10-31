<?php

namespace App\Livewire\Modulos;

use App\Livewire\ListaComponent;
use App\Livewire\Traits\ConFiltroEstado;
use App\Livewire\Traits\ConHistorialEstadoModal;
use App\Models\Estado;
use App\Models\Modulo;
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

    #[On('modulo-guardado')]
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
        return Modulo::class;
    }

    protected function columnasOrdenables(): ?array
    {
        return ['nombre'];
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
            'nombre'      => __('Nombre'),
            'asignaturas' => __('Asignaturas'),
            'estado'      => __('Estado'),
        ];
    }

    public function confirmarBaja($id)
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Dar de baja el módulo?'),
            'text'               => __('Se marcará como inactivo.'),
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
        $modulo = Modulo::where('horario_id', session('horario_actual_id'))->find($id);

        if ($modulo) {
            $modulo->update(['estado_id' => Modulo::ESTADO_BAJA]);
            $this->dispatch('toast-success', ['title' => __('Módulo dado de baja')]);
        }
    }

    public function confirmarReactivar($id)
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Reactivar el módulo?'),
            'text'               => __('Se marcará como activo.'),
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
        $modulo = Modulo::where('horario_id', session('horario_actual_id'))->find($id);

        if ($modulo) {
            $modulo->update(['estado_id' => Modulo::ESTADO_ACTIVO]);
            $this->dispatch('toast-success', ['title' => __('Módulo reactivado')]);
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
            Modulo::with('estado')
                ->withCount(['historialEstados', 'asignaturas'])
                ->where('horario_id', session('horario_actual_id'))
        )
            ->when($search, fn ($q) => $q->where('nombre', 'like', "%{$search}%"))
            ->orderBy($this->sort, $this->direction)
            ->orderBy('id')
            ->paginate($this->lineasXPagina);

        return view('livewire.modulos.lista', compact('items'));
    }
}
