<?php

namespace App\Livewire\Horarios;

use App\Models\Asignatura;
use App\Models\Horario;
use App\Models\HorarioSesion;
use App\Models\Jornada;
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

        $horario = Horario::with(['jornadas', 'sesiones'])->find($this->horarioId);

        $this->diasConfig = [];
        $this->maxSlots = 0;
        $this->asignaciones = [];

        foreach ($horario->jornadasPorDia() as $diaSemana => $jornadas) {
            $combinado = Jornada::combinarSlots($jornadas, $horario->duracion_sesion_minutos ?? 0);

            $this->diasConfig[$diaSemana] = [
                'nombre' => DiaSemana::from($diaSemana)->nombre(),
                'num_sesiones' => $combinado['num_sesiones'],
                'slots' => $combinado['slots'],
            ];

            $this->maxSlots = max($this->maxSlots, count($combinado['slots']));

            $this->asignaciones[$diaSemana] = array_fill(1, $combinado['num_sesiones'], '');
        }

        foreach ($horario->sesiones as $sesion) {
            $this->asignaciones[$sesion->dia_semana][$sesion->sesion_numero] = $sesion->asignatura_id;
        }

        $this->abrir = true;
    }

    public function guardar()
    {
        DB::transaction(function () {
            foreach ($this->diasConfig as $dia => $config) {
                for ($s = 1; $s <= $config['num_sesiones']; $s++) {
                    $asignaturaId = $this->asignaciones[$dia][$s] ?? '';

                    if ($asignaturaId !== '') {
                        HorarioSesion::updateOrCreate(
                            ['horario_id' => $this->horarioId, 'dia_semana' => $dia, 'sesion_numero' => $s],
                            ['asignatura_id' => $asignaturaId],
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
            'asignaturas' => Asignatura::where('horario_id', $this->horarioId)->orderBy('nombre')->get(),
        ]);
    }
}
