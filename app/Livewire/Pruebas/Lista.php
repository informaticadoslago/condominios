<?php

namespace App\Livewire\Pruebas;

use App\Livewire\ListaComponent;
use App\Livewire\Traits\ConBajaYBorradoFisico;
use App\Livewire\Traits\ConFiltroEstado;
use App\Livewire\Traits\ConHistorialEstadoModal;
use App\Models\Asignatura;
use App\Models\Curso;
use App\Models\Estado;
use App\Models\Evaluacion;
use App\Models\Prueba;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

/**
 * Pruebas evaluables de una asignatura de un horario de profesor, filtradas por curso y
 * evaluación. De fábrica (al entrar y al borrar el filtro) son el curso y la evaluación
 * en los que cae hoy; si hoy no cae en ninguno, el filtro queda vacío.
 */
class Lista extends ListaComponent
{
    use ConBajaYBorradoFisico;
    use ConFiltroEstado;
    use ConHistorialEstadoModal;

    #[Locked]
    public int $asignaturaId;

    public function mount(Asignatura $asignatura)
    {
        // Solo asignaturas del horario abierto, y solo si es de profesor.
        abort_unless(
            $asignatura->horario_id == session('horario_actual_id') && $asignatura->horario?->esDeProfesor(),
            404
        );

        $this->asignaturaId = $asignatura->id;
        // De fábrica, en el orden elegido a mano (arrastrando): el de impresión.
        $this->sort         = 'orden';
        $this->direction    = 'asc';
    }

    #[On('prueba-guardado')]
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
        return Prueba::class;
    }

    protected function columnasOrdenables(): ?array
    {
        return ['orden', 'nombre', 'fecha', 'puntuacion_maxima', 'peso'];
    }

    public function definicionesFiltro(): array
    {
        $horarioId = session('horario_actual_id');
        $cursoId   = $this->filtros['curso_id'] ?? '';

        return [
            [
                // Sin caché: los cursos son de cada horario.
                'clave'    => 'curso_id',
                'etiqueta' => __('Curso'),
                'tipo'     => 'select',
                'opciones' => ['' => '--'] + Curso::where('horario_id', $horarioId)
                    ->orderBy('fecha_inicio')
                    ->pluck('nombre', 'id')
                    ->all(),
                'neutro'   => '',
                'aplicar'  => fn ($query, $valor) => $query->whereHas('evaluacion', fn ($q) => $q->where('curso_id', $valor)),
            ],
            [
                // Las del curso elegido; sin curso no hay evaluaciones que ofrecer.
                'clave'    => 'evaluacion_id',
                'etiqueta' => __('Evaluación'),
                'tipo'     => 'select',
                'opciones' => ['' => '--'] + ($cursoId
                    ? Evaluacion::where('curso_id', $cursoId)->orderBy('fecha_inicio')->pluck('nombre', 'id')->all()
                    : []),
                'neutro'   => '',
                'aplicar'  => fn ($query, $valor) => $query->where('evaluacion_id', $valor),
            ],
            $this->filtroEstado(),
        ];
    }

    /** De fábrica, el curso y la evaluación en los que cae hoy; vacíos si no cae en ninguno. */
    protected function filtrosPorDefecto(): array
    {
        $curso      = Curso::actual((int) session('horario_actual_id'));
        $evaluacion = $curso ? Evaluacion::actual($curso->id) : null;

        return array_merge(parent::filtrosPorDefecto(), [
            'curso_id'      => $curso?->id ?? '',
            'evaluacion_id' => $evaluacion?->id ?? '',
        ]);
    }

    /**
     * El curso y la evaluación no se recuerdan de una visita a otra: siempre se entra con
     * los actuales. Lo demás (columnas, orden, estado…) sí se recupera.
     */
    protected function cargarPreferencias(): void
    {
        parent::cargarPreferencias();

        $this->filtros['curso_id']      = $this->porDefecto['filtros']['curso_id'];
        $this->filtros['evaluacion_id'] = $this->porDefecto['filtros']['evaluacion_id'];
    }

    /** Al cambiar de curso, la evaluación elegida ya no vale: era del otro curso. */
    public function updatedFiltros($valor = null, $clave = null): void
    {
        if ($clave === 'curso_id') {
            $this->filtros['evaluacion_id'] = '';
        }

        parent::updatedFiltros();
    }

    public function columnasDisponibles(): array
    {
        return [
            'nombre'            => __('Nombre'),
            'fecha'             => __('Fecha'),
            'evaluacion'        => __('Evaluación'),
            'puntuacion_maxima' => __('Nota sobre'),
            'peso'              => __('Peso'),
            'notas'             => __('Notas'),
            'estado'            => __('Estado'),
        ];
    }

    protected function registroDeLaLista($id): ?Model
    {
        return Prueba::where('asignatura_id', $this->asignaturaId)->find($id);
    }

    /** Una prueba con notas no se borra. */
    protected function enUso(Model $registro): bool
    {
        return $registro->notas()->exists();
    }

    /**
     * Solo se arrastra cuando la lista se ve en el orden manual y sin búsqueda: con otro
     * orden en pantalla, soltar una fila «aquí» no diría dónde queda de verdad.
     */
    private function sePuedeArrastrar(): bool
    {
        return $this->sort === 'orden' && $this->direction === 'asc' && trim($this->search ?? '') === '';
    }

    /**
     * Arrastrar una prueba sobre otra: si se lleva hacia abajo queda justo después de
     * ella, y si se lleva hacia arriba, justo antes. Se renumeran todas las de la
     * asignatura (también las que el filtro no enseña), que conservan su orden relativo.
     */
    public function mover($id, $sobreId): void
    {
        if (! $this->sePuedeArrastrar() || $id == $sobreId) {
            return;
        }

        $ids = Prueba::where('asignatura_id', $this->asignaturaId)->orderBy('orden')->orderBy('id')->pluck('id')->all();
        $desde = array_search((int) $id, $ids, true);
        $hasta = array_search((int) $sobreId, $ids, true);

        if ($desde === false || $hasta === false) {
            return;
        }

        array_splice($ids, $desde, 1);
        array_splice($ids, $hasta, 0, [(int) $id]);

        DB::transaction(function () use ($ids) {
            foreach ($ids as $posicion => $pruebaId) {
                Prueba::whereKey($pruebaId)->update(['orden' => $posicion + 1]);
            }
        });
    }

    /**
     * Suma de los pesos de las pruebas activas de la evaluación elegida en el filtro, o
     * null si no hay ninguna elegida. No tiene que llegar a 100 (pueden faltar pruebas por
     * crear), pero pasar de 100 se avisa en rojo.
     */
    private function sumaPesos(): ?float
    {
        $evaluacionId = $this->filtros['evaluacion_id'] ?? '';

        return $evaluacionId
            ? (float) Prueba::where('asignatura_id', $this->asignaturaId)
                ->where('evaluacion_id', $evaluacionId)
                ->where('estado_id', Prueba::ESTADO_ACTIVO)
                ->sum('peso')
            : null;
    }

    public function render()
    {
        $search = trim($this->search ?? '');

        $items = $this->aplicarFiltros(
            Prueba::with(['estado', 'evaluacion.curso'])
                ->withCount(['historialEstados', 'notas'])
                ->where('asignatura_id', $this->asignaturaId)
        )
            ->when($search, fn ($q) => $q->where('nombre', 'like', "%{$search}%"))
            ->orderBy($this->sort, $this->direction)
            ->orderBy('id')
            ->paginate($this->lineasXPagina);

        return view('livewire.pruebas.lista', [
            'items'      => $items,
            'asignatura' => Asignatura::with('modulo')->find($this->asignaturaId),
            'sumaPesos'  => $this->sumaPesos(),
            'arrastrar'  => $this->sePuedeArrastrar(),
        ]);
    }
}
