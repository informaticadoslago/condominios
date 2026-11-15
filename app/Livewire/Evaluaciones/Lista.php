<?php

namespace App\Livewire\Evaluaciones;

use App\Livewire\ListaComponent;
use App\Livewire\Traits\ConBajaYBorradoFisico;
use App\Livewire\Traits\ConFiltroEstado;
use App\Livewire\Traits\ConHistorialEstadoModal;
use App\Models\Curso;
use App\Models\Estado;
use App\Models\Evaluacion;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

/** Evaluaciones de un curso de un horario de profesor. */
class Lista extends ListaComponent
{
    use ConBajaYBorradoFisico;
    use ConFiltroEstado;
    use ConHistorialEstadoModal;

    #[Locked]
    public int $cursoId;

    public function mount(Curso $curso)
    {
        // Solo cursos del horario abierto, y solo si es de profesor.
        abort_unless(
            $curso->horario_id == session('horario_actual_id') && $curso->horario?->esDeProfesor(),
            404
        );

        $this->cursoId   = $curso->id;
        $this->sort      = 'fecha_inicio';
        $this->direction = 'asc';
    }

    #[On('evaluacion-guardado')]
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
        return Evaluacion::class;
    }

    protected function columnasOrdenables(): ?array
    {
        return ['nombre', 'fecha_inicio', 'fecha_fin'];
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
            'nombre'       => __('Nombre'),
            'fecha_inicio' => __('Fecha de inicio'),
            'fecha_fin'    => __('Fecha de fin'),
            'estado'       => __('Estado'),
        ];
    }

    protected function registroDeLaLista($id): ?Model
    {
        return Evaluacion::where('curso_id', $this->cursoId)->find($id);
    }

    /** Una evaluación con pruebas no se borra. */
    protected function enUso(Model $registro): bool
    {
        return $registro->pruebas()->exists();
    }

    public function render()
    {
        $search = trim($this->search ?? '');

        $items = $this->aplicarFiltros(
            Evaluacion::with('estado')
                ->withCount('historialEstados')
                ->where('curso_id', $this->cursoId)
        )
            ->when($search, fn ($q) => $q->where('nombre', 'like', "%{$search}%"))
            ->orderBy($this->sort, $this->direction)
            ->orderBy('id')
            ->paginate($this->lineasXPagina);

        return view('livewire.evaluaciones.lista', [
            'items' => $items,
            'curso' => Curso::find($this->cursoId),
        ]);
    }
}
