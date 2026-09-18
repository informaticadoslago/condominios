<?php

namespace App\Livewire\Horarios;

use App\Models\Horario;
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
        $this->horario = Horario::with(['dias', 'sesiones.asignatura'])->find((int) session('horario_actual_id'));
    }

    public function render()
    {
        $diasConfig = [];
        $maxSlots   = 0;

        foreach ($this->horario?->dias->sortBy('dia_semana') ?? [] as $dia) {
            $slots = $dia->calcularSlotsAlineados($this->horario->duracion_sesion_minutos ?? 0);

            $diasConfig[$dia->dia_semana] = [
                'nombre' => DiaSemana::from($dia->dia_semana)->nombre(),
                'slots'  => $slots,
            ];

            $maxSlots = max($maxSlots, count($slots));
        }

        $asignaciones = [];
        foreach ($this->horario?->sesiones ?? [] as $sesion) {
            if ($sesion->asignatura) {
                $asignaciones[$sesion->dia_semana][$sesion->sesion_numero] = [
                    'nombre' => $sesion->asignatura->nombre,
                    'fondo'  => $sesion->asignatura->colorFondo(),
                    'texto'  => $sesion->asignatura->colorTexto(),
                ];
            }
        }

        return view('livewire.horarios.rejilla', compact('diasConfig', 'maxSlots', 'asignaciones'));
    }
}
