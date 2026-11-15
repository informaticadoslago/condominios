<?php

namespace App\Livewire\Alumnos;

use App\Livewire\ListaComponent;
use App\Livewire\Traits\ConFiltroEstado;
use App\Livewire\Traits\ConHistorialEstadoModal;
use App\Models\Asignatura;
use App\Models\AsignaturaAlumno;
use App\Models\Estado;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

/**
 * Alumnos de una asignatura de un horario de profesor. Cada fila es un apunte
 * (AsignaturaAlumno): el alumno es del horario y puede estar en varias asignaturas.
 */
class Lista extends ListaComponent
{
    use ConFiltroEstado;
    use ConHistorialEstadoModal;

    #[Locked]
    public int $asignaturaId;

    /** Columna de la lista => columna de la consulta (alumnos va unida al apunte). */
    private const ORDEN = [
        'apellido1'        => 'alumnos.apellido1',
        'apellido2'        => 'alumnos.apellido2',
        'nombre'           => 'alumnos.nombre',
        'fecha_nacimiento' => 'alumnos.fecha_nacimiento',
        'grupo'            => 'asignatura_alumnos.grupo',
    ];

    public function mount(Asignatura $asignatura)
    {
        // Solo asignaturas del horario abierto, y solo si es de profesor.
        abort_unless(
            $asignatura->horario_id == session('horario_actual_id') && $asignatura->horario?->esDeProfesor(),
            404
        );

        $this->asignaturaId = $asignatura->id;
        $this->sort         = 'apellido1';
        $this->direction    = 'asc';
    }

    #[On('alumno-guardado')]
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
        return AsignaturaAlumno::class;
    }

    protected function columnasOrdenables(): ?array
    {
        return array_keys(self::ORDEN);
    }

    public function definicionesFiltro(): array
    {
        return [
            $this->filtroEstado('asignatura_alumnos.estado_id'),
        ];
    }

    public function columnasDisponibles(): array
    {
        return [
            'apellido1'        => __('Primer apellido'),
            'apellido2'        => __('Segundo apellido'),
            'nombre'           => __('Nombre'),
            'fecha_nacimiento' => __('Fecha de nacimiento'),
            'grupo'            => __('Grupo'),
            'estado'           => __('Estado'),
        ];
    }

    /** El apunte, solo si es de la asignatura de esta pantalla. */
    private function apunte($id): ?AsignaturaAlumno
    {
        return AsignaturaAlumno::where('asignatura_id', $this->asignaturaId)->find($id);
    }

    public function confirmarBaja($id)
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Dar de baja al alumno en la asignatura?'),
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
        if ($apunte = $this->apunte($id)) {
            $apunte->update(['estado_id' => AsignaturaAlumno::ESTADO_BAJA]);
            $this->dispatch('toast-success', ['title' => __('Alumno dado de baja')]);
        }
    }

    public function confirmarReactivar($id)
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Reactivar al alumno en la asignatura?'),
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
        if ($apunte = $this->apunte($id)) {
            $apunte->update(['estado_id' => AsignaturaAlumno::ESTADO_ACTIVO]);
            $this->dispatch('toast-success', ['title' => __('Alumno reactivado')]);
        }
    }

    #[On('bajaCancelada')]
    public function bajaCancelada($id = null)
    {
        // el usuario canceló; no hacemos nada
    }

    /** Mayús+clic sobre un apunte inactivo: borrado físico, sin vuelta atrás. */
    public function confirmarEliminarFisico($id)
    {
        $apunte = $this->apunte($id);

        if (! $apunte || $apunte->estado_id == AsignaturaAlumno::ESTADO_ACTIVO) {
            return;
        }

        // Con notas en alguna prueba no se borra.
        if ($apunte->notas()->exists()) {
            $this->dispatch('toast-error', ['title' => __('No se puede borrar: el alumno tiene notas en esta asignatura')]);

            return;
        }

        $this->dispatch('swalConfirm', [
            'title'              => __('¿Borrar definitivamente?'),
            'text'               => $apunte->alumno->apuntes()->count() > 1
                ? __('Se borra al alumno de esta asignatura, con su historial de estados. No se puede deshacer.')
                : __('Es la única asignatura del alumno: se borra también el alumno del horario. No se puede deshacer.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#d33',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, borrar definitivamente'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarEliminarFisico',
            'cancelCallback'     => 'bajaCancelada',
            'id'                 => $id,
        ]);
    }

    #[On('ejecutarEliminarFisico')]
    public function ejecutarEliminarFisico($id)
    {
        $apunte = $this->apunte($id);

        if (! $apunte || $apunte->estado_id == AsignaturaAlumno::ESTADO_ACTIVO) {
            return;
        }

        // Con notas en alguna prueba no se borra.
        if ($apunte->notas()->exists()) {
            $this->dispatch('toast-error', ['title' => __('No se puede borrar: el alumno tiene notas en esta asignatura')]);

            return;
        }

        DB::transaction(fn () => $apunte->borrarDefinitivamente());
        $this->dispatch('toast-success', ['title' => __('Alumno borrado definitivamente')]);
    }

    public function render()
    {
        $search = trim($this->search ?? '');

        $query = $this->aplicarFiltros(
            AsignaturaAlumno::with(['alumno', 'estado'])
                ->withCount('historialEstados')
                ->join('alumnos', 'alumnos.id', '=', 'asignatura_alumnos.alumno_id')
                ->select('asignatura_alumnos.*')
                ->where('asignatura_alumnos.asignatura_id', $this->asignaturaId)
        );

        // Cada palabra buscada debe estar en el nombre, en un apellido o en el grupo.
        foreach ($search === '' ? [] : preg_split('/\s+/', $search) as $palabra) {
            $query->where(fn ($q) => $q
                ->where('alumnos.nombre', 'like', "%{$palabra}%")
                ->orWhere('alumnos.apellido1', 'like', "%{$palabra}%")
                ->orWhere('alumnos.apellido2', 'like', "%{$palabra}%")
                ->orWhere('asignatura_alumnos.grupo', 'like', "%{$palabra}%"));
        }

        $items = $query
            ->orderBy(self::ORDEN[$this->sort] ?? self::ORDEN['apellido1'], $this->direction)
            ->orderBy('alumnos.apellido1')
            ->orderBy('alumnos.apellido2')
            ->orderBy('alumnos.nombre')
            ->orderBy('asignatura_alumnos.id')
            ->paginate($this->lineasXPagina);

        return view('livewire.alumnos.lista', [
            'items'      => $items,
            'asignatura' => Asignatura::with('modulo')->find($this->asignaturaId),
        ]);
    }
}
