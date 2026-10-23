<?php

namespace App\Livewire\Examenes;

use App\Livewire\ListaComponent;
use App\Models\Asignatura;
use App\Models\Examen;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{
    public function mount()
    {
        // De fábrica, en orden cronológico: el más cercano primero.
        $this->sort      = 'fecha';
        $this->direction = 'asc';
    }

    #[On('examen-guardado')]
    public function refrescar()
    {
        // el evento fuerza el re-render de la lista
    }

    protected function columnasOrdenables(): ?array
    {
        return ['fecha', 'hora'];
    }

    public function columnasDisponibles(): array
    {
        return [
            'fecha'      => __('Fecha'),
            'dia'        => __('Día'),
            'hora'       => __('Hora'),
            'asignatura' => __('Asignatura'),
        ];
    }

    public function definicionesFiltro(): array
    {
        return [
            [
                // Sin caché: las asignaturas son de cada horario y se dan de alta a menudo.
                'clave'    => 'asignatura_id',
                'etiqueta' => __('Asignatura'),
                'tipo'     => 'select',
                'opciones' => [0 => __('Todas')] + Asignatura::where('horario_id', session('horario_actual_id'))
                    ->orderBy('nombre')
                    ->pluck('nombre', 'id')
                    ->all(),
                'neutro'   => 0,
                'aplicar'  => fn ($query, $valor) => $query->where('asignatura_id', $valor),
            ],
        ];
    }

    public function confirmarEliminar($id)
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Eliminar el examen?'),
            'text'               => __('Se quitará del calendario de exámenes.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#d33',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, eliminar'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarEliminarExamen',
            'cancelCallback'     => 'eliminarExamenCancelado',
            'id'                 => $id,
        ]);
    }

    #[On('ejecutarEliminarExamen')]
    public function ejecutarEliminar($id)
    {
        $examen = Examen::where('horario_id', session('horario_actual_id'))->find($id);

        if ($examen) {
            $examen->delete();
            $this->dispatch('toast-success', ['title' => __('Examen eliminado')]);
        }
    }

    #[On('eliminarExamenCancelado')]
    public function eliminarCancelado($id = null)
    {
        // el usuario canceló; no hacemos nada
    }

    public function render()
    {
        $items = $this->aplicarFiltros(
            Examen::with('asignatura')
                ->where('horario_id', session('horario_actual_id'))
        )
            ->orderBy($this->sort, $this->direction)
            ->orderBy('fecha')
            ->orderBy('hora')
            ->orderBy('id')
            ->paginate($this->lineasXPagina);

        return view('livewire.examenes.lista', compact('items'));
    }
}
