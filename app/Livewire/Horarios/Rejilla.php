<?php

namespace App\Livewire\Horarios;

use App\Models\Horario;
use App\Models\Jornada;
use App\Support\DiaSemana;
use Carbon\Carbon;
use Livewire\Attributes\On;
use Livewire\Component;

class Rejilla extends Component
{
    public ?Horario $horario = null;

    public function mount()
    {
        $this->cargar();
    }

    #[On('rejilla-guardada')]
    public function cargar(): void
    {
        $this->horario = Horario::with(['jornadas', 'sesiones.asignatura'])->find((int) session('horario_actual_id'));
    }

    public function render()
    {
        $diasConfig = [];
        $maxSlots = 0;

        $minutosReferencia = $this->horario?->alinear_horas ? $this->horario->minutosReferenciaJornadas() : null;
        $duracionSesion = $this->horario?->duracion_sesion_minutos ?? 0;

        foreach ($this->horario?->jornadasPorDia() ?? collect() as $diaSemana => $jornadas) {
            $combinado = Jornada::combinarSlots($jornadas, $duracionSesion, $minutosReferencia);

            // 'fin' es para la vista del móvil (un día cada vez): ahí cada sesión lleva su
            // propia hora, no la de la fila, que sin alinear_horas puede ser de otro día.
            $slots = array_map(fn (array $slot) => $slot + [
                'fin' => Carbon::createFromFormat('H:i', $slot['hora'])
                    ->addMinutes($slot['tipo'] === 'recreo' ? $slot['duracion'] : $duracionSesion)
                    ->format('H:i'),
            ], $combinado['slots']);
            ksort($slots);

            $diasConfig[$diaSemana] = [
                'nombre' => DiaSemana::from($diaSemana)->nombre(),
                'abreviatura' => DiaSemana::from($diaSemana)->abreviatura(),
                'slots' => $slots,
            ];

            $maxSlots = max($maxSlots, $combinado['total_filas']);
        }

        $asignaciones = [];
        foreach ($this->horario?->sesiones ?? [] as $sesion) {
            if ($sesion->asignatura) {
                $asignaciones[$sesion->dia_semana][$sesion->sesion_numero] = [
                    'nombre' => $sesion->asignatura->nombre,
                    'fondo' => $sesion->asignatura->colorFondo(),
                    'texto' => $sesion->asignatura->colorTexto(),
                ];
            }
        }

        $horasFilas = Jornada::horasPorFila($diasConfig, $maxSlots, $duracionSesion);

        // En el móvil se abre en el día de hoy si tiene clase; si no, en el primero.
        $diaInicial = isset($diasConfig[now()->dayOfWeekIso]) ? now()->dayOfWeekIso : array_key_first($diasConfig);

        return view('livewire.horarios.rejilla', compact('diasConfig', 'maxSlots', 'asignaciones', 'horasFilas', 'diaInicial'));
    }
}
