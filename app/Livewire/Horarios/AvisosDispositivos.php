<?php

namespace App\Livewire\Horarios;

use App\Models\HorarioAvisoDispositivo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * "Configuración avisos" del horario semanal: a qué dispositivos del usuario van los
 * avisos de sesión de este horario (las campanas de Editar). Con "Todos", a todos,
 * también a los que añada después; si no, solo a los marcados, y uno nuevo no recibe
 * nada hasta que se marque.
 */
class AvisosDispositivos extends Component
{
    public bool $abrir = false;

    public int $horarioId;

    public bool $todos = true;

    /** Ids (como texto, los da así el wire:model de los checkbox) de los dispositivos marcados. */
    public array $marcados = [];

    public function mount(): void
    {
        $this->horarioId = (int) session('horario_actual_id');
    }

    #[Computed]
    public function dispositivos()
    {
        return Auth::user()->pushSubscriptions()->orderBy('created_at')->get();
    }

    #[On('abrir-avisos-dispositivos')]
    public function abrirModal(): void
    {
        $this->horarioId = (int) session('horario_actual_id');
        unset($this->dispositivos);

        $guardados = HorarioAvisoDispositivo::where('user_id', Auth::id())
            ->where('horario_id', $this->horarioId)
            ->pluck('push_subscription_id');

        $this->todos = $guardados->isEmpty();
        $this->marcados = ($this->todos ? $this->dispositivos->pluck('id') : $guardados)->map(fn ($id) => (string) $id)->all();

        $this->resetValidation();
        $this->abrir = true;
    }

    /** Con "Todos" se ven todos marcados; al quitarlo se parte de ahí para desmarcar. */
    public function updatedTodos(): void
    {
        if ($this->todos) {
            $this->marcados = $this->dispositivos->pluck('id')->map(fn ($id) => (string) $id)->all();
        }

        $this->resetValidation();
    }

    public function guardar(): void
    {
        $marcados = $this->dispositivos->pluck('id')->intersect(array_map('intval', $this->marcados))->values();

        if (! $this->todos && $marcados->isEmpty()) {
            $this->addError('marcados', __('Marca al menos un dispositivo.'));

            return;
        }

        DB::transaction(function () use ($marcados) {
            HorarioAvisoDispositivo::where('user_id', Auth::id())->where('horario_id', $this->horarioId)->delete();

            if (! $this->todos) {
                foreach ($marcados as $id) {
                    HorarioAvisoDispositivo::create([
                        'user_id' => Auth::id(),
                        'horario_id' => $this->horarioId,
                        'push_subscription_id' => $id,
                    ]);
                }
            }
        });

        $this->dispatch('toast-success', ['title' => __('Configuración de avisos guardada')]);
        $this->cerrar();
    }

    public function cerrar(): void
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.horarios.avisos-dispositivos');
    }
}
