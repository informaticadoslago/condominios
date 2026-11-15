<?php

namespace App\Livewire\Cursos;

use App\Livewire\ListaComponent;
use App\Livewire\Traits\ConBajaYBorradoFisico;
use App\Livewire\Traits\ConFiltroEstado;
use App\Livewire\Traits\ConHistorialEstadoModal;
use App\Models\Curso;
use App\Models\Estado;
use App\Models\Horario;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

/** Cursos de un horario de profesor. */
class Lista extends ListaComponent
{
    use ConBajaYBorradoFisico;
    use ConFiltroEstado;
    use ConHistorialEstadoModal;

    public function mount()
    {
        abort_unless(Horario::find(session('horario_actual_id'))?->esDeProfesor(), 404);

        $this->sort      = 'fecha_inicio';
        $this->direction = 'asc';
    }

    #[On('curso-guardado')]
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
        return Curso::class;
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
            'evaluaciones' => __('Evaluaciones'),
            'estado'       => __('Estado'),
        ];
    }

    protected function registroDeLaLista($id): ?Model
    {
        return Curso::where('horario_id', session('horario_actual_id'))->find($id);
    }

    /** Un curso con evaluaciones no se borra. */
    protected function enUso(Model $registro): bool
    {
        return $registro->evaluaciones()->exists();
    }

    public function render()
    {
        $search = trim($this->search ?? '');

        $items = $this->aplicarFiltros(
            Curso::with('estado')
                ->withCount(['historialEstados', 'evaluaciones'])
                ->where('horario_id', session('horario_actual_id'))
        )
            ->when($search, fn ($q) => $q->where('nombre', 'like', "%{$search}%"))
            ->orderBy($this->sort, $this->direction)
            ->orderBy('id')
            ->paginate($this->lineasXPagina);

        return view('livewire.cursos.lista', compact('items'));
    }
}
