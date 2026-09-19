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
        $this->horario = Horario::with('jornadas')->find((int) session('horario_actual_id'));
    }

    public function render()
    {
        $jornadas = $this->horario?->jornadas->sortBy('hora_inicio')->values() ?? collect();

        return view('livewire.horarios.dias-sesiones', [
            'jornadas' => $jornadas,
        ]);
    }
}
