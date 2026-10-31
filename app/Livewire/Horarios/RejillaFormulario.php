<?php

namespace App\Livewire\Horarios;

use App\Models\Asignatura;
use App\Models\Horario;
use App\Models\HorarioSesion;
use App\Models\Jornada;
use App\Services\Horarios\ColocacionSesiones;
use App\Support\DiaSemana;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class RejillaFormulario extends Component
{
    public bool $abrir = false;

    public int $horarioId;

    /** [dia_semana => ['nombre' => .., 'num_sesiones' => n, 'slots' => calcularSlots()]], solo días con clase. */
    public array $diasConfig = [];

    /** Fila más larga de la rejilla: el máximo de slots (sesiones + recreo) entre los días activos. */
    public int $maxSlots = 0;

    /** [dia_semana][sesion_numero] => asignatura_id ('' si ninguna), para el wire:model de cada select. */
    public array $asignaciones = [];

    /** Solo en horarios de profesor: cada sesión puede llevar su hora de inicio a mano. */
    public bool $esDeProfesor = false;

    /** [dia_semana][sesion_numero] => 'H:i' a mano ('' = la que le toca por la jornada). */
    public array $horas = [];

    /**
     * El componente se pinta en la página aunque el modal esté cerrado (@livewire lo
     * incluye siempre), y render() usa $horarioId (consulta las asignaturas): hace
     * falta un valor desde el primer render, no solo cuando abrirModal() lo rellena.
     */
    public function mount(): void
    {
        $this->horarioId = (int) session('horario_actual_id');
    }

    #[On('abrir-editar-rejilla')]
    public function abrirModal(): void
    {
        $this->horarioId = (int) session('horario_actual_id');

        $horario = Horario::with(['jornadas.recreos', 'sesiones'])->find($this->horarioId);

        $this->diasConfig = [];
        $this->maxSlots = 0;
        $this->asignaciones = [];
        $this->horas = [];
        $this->esDeProfesor = $horario->esDeProfesor();

        $minutosReferencia = $horario->alinear_horas ? $horario->minutosReferenciaJornadas() : null;

        foreach ($horario->jornadasPorDia() as $diaSemana => $jornadas) {
            $combinado = Jornada::combinarSlots($jornadas, $horario->duracion_sesion_minutos ?? 0, $minutosReferencia);

            $this->diasConfig[$diaSemana] = [
                'nombre' => DiaSemana::from($diaSemana)->nombre(),
                'num_sesiones' => $combinado['num_sesiones'],
                'slots' => $combinado['slots'],
            ];

            $this->maxSlots = max($this->maxSlots, $combinado['total_filas']);

            $this->asignaciones[$diaSemana] = array_fill(1, $combinado['num_sesiones'], '');
            $this->horas[$diaSemana] = array_fill(1, $combinado['num_sesiones'], '');
        }

        foreach ($horario->sesiones as $sesion) {
            $this->asignaciones[$sesion->dia_semana][$sesion->sesion_numero] = $sesion->asignatura_id;
            $this->horas[$sesion->dia_semana][$sesion->sesion_numero] = $sesion->hora_inicio ? substr($sesion->hora_inicio, 0, 5) : '';
        }

        $this->resetValidation();
        $this->abrir = true;
    }

    public function guardar()
    {
        $horario = Horario::with(['jornadas.recreos', 'sesiones'])->find($this->horarioId);
        $this->esDeProfesor = $horario->esDeProfesor();
        $colocacion = $this->esDeProfesor ? new ColocacionSesiones($horario) : null;
        $manuales = [];

        if ($colocacion) {
            $this->validate(
                ['horas.*.*' => ['nullable', 'date_format:H:i']],
                ['date_format' => __('No es una hora válida.')],
            );

            // Cómo quedarían las sesiones con asignatura de cada día con las horas del
            // formulario: ninguna puede pisar a otra ni salirse de su jornada.
            foreach ($this->diasConfig as $dia => $config) {
                $manuales[$dia] = [];

                for ($s = 1; $s <= $config['num_sesiones']; $s++) {
                    if (($this->asignaciones[$dia][$s] ?? '') !== '') {
                        $hora = $this->horas[$dia][$s] ?? '';
                        $manuales[$dia][$s] = $hora !== '' ? Jornada::minutosDesdeMedianoche($hora) : null;
                    }
                }

                foreach ($colocacion->errores($dia, $manuales[$dia]) as $numero => $mensaje) {
                    $this->addError("horas.$dia.$numero", $mensaje);
                }
            }

            if ($this->getErrorBag()->isNotEmpty()) {
                return;
            }
        }

        DB::transaction(function () use ($colocacion, $manuales) {
            foreach ($this->diasConfig as $dia => $config) {
                for ($s = 1; $s <= $config['num_sesiones']; $s++) {
                    $asignaturaId = $this->asignaciones[$dia][$s] ?? '';

                    if ($asignaturaId !== '') {
                        $valores = ['asignatura_id' => $asignaturaId];

                        if ($colocacion) {
                            // A su hora de siempre, la sesión vuelve a "automática".
                            $manual = $manuales[$dia][$s] ?? null;
                            $valores['hora_inicio'] = $manual !== null && $manual !== $colocacion->inicioPorDefecto($dia, $s)
                                ? ColocacionSesiones::hora($manual)
                                : null;
                        }

                        HorarioSesion::updateOrCreate(
                            ['horario_id' => $this->horarioId, 'dia_semana' => $dia, 'sesion_numero' => $s],
                            $valores,
                        );
                    } else {
                        HorarioSesion::where('horario_id', $this->horarioId)
                            ->where('dia_semana', $dia)
                            ->where('sesion_numero', $s)
                            ->delete();
                    }
                }
            }
        });

        $this->dispatch('toast-success', ['title' => __('Horario guardado')]);
        $this->dispatch('rejilla-guardada');
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.horarios.rejilla-formulario', [
            'asignaturas' => Asignatura::with('modulo')->where('horario_id', $this->horarioId)->orderBy('nombre')->get(),
        ]);
    }
}
