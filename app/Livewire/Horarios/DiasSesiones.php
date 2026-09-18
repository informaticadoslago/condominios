<?php

namespace App\Livewire\Horarios;

use App\Models\Horario;
use Livewire\Attributes\On;
use Livewire\Component;

class DiasSesiones extends Component
{
    public ?Horario $horario = null;

    public function mount()
    {
        $this->cargar();
    }

    #[On('dias-sesiones-guardado')]
    public function cargar(): void
    {
        $this->horario = Horario::with('dias')->find((int) session('horario_actual_id'));
    }

    public function render()
    {
        $dias = $this->horario?->dias->sortBy('dia_semana')->values() ?? collect();

        $activos = $dias->pluck('dia_semana')->sort()->values()->all();

        $tipoDiasLabel = match (true) {
            $activos === [1, 2, 3, 4, 5]       => __('Lunes a viernes'),
            $activos === [1, 2, 3, 4, 5, 6, 7] => __('Lunes a domingo'),
            default                            => __('Personalizado'),
        };

        return view('livewire.horarios.dias-sesiones', [
            'dias'          => $dias,
            'tipoDiasLabel' => $tipoDiasLabel,
        ]);
    }
}
