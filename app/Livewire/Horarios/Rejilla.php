<?php

namespace App\Livewire\Horarios;

use App\Models\Horario;
use App\Models\Jornada;
use App\Support\DiaSemana;
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

        foreach ($this->horario?->jornadasPorDia() ?? collect() as $diaSemana => $jornadas) {
            $combinado = Jornada::combinarSlots($jornadas, $this->horario->duracion_sesion_minutos ?? 0);

            $diasConfig[$diaSemana] = [
                'nombre' => DiaSemana::from($diaSemana)->nombre(),
                'slots' => $combinado['slots'],
            ];

            $maxSlots = max($maxSlots, count($combinado['slots']));
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

        return view('livewire.horarios.rejilla', compact('diasConfig', 'maxSlots', 'asignaciones'));
    }
}
