<?php

namespace App\Livewire\GruposDeReparto;

use App\Models\GrupoDeReparto;
use App\Models\Inmueble;
use Livewire\Attributes\On;
use Livewire\Component;

class Miembros extends Component
{
    public bool $abrir = false;
    public ?int $grupoId = null;
    public string $grupoNombre = '';

    /** [inmueble_id => ['seleccionado' => bool, 'coeficiente' => ?string]] */
    public array $miembros = [];

    #[On('abrir-miembros-grupo-de-reparto')]
    public function abrirMiembros($id)
    {
        $grupo = GrupoDeReparto::where('comunidad_id', session('comunidad_actual_id'))->find($id);
        if (! $grupo) {
            return;
        }

        $actuales = $grupo->inmuebles()->get()->keyBy('id');

        $this->grupoId     = $grupo->id;
        $this->grupoNombre = $grupo->nombre;
        $this->miembros    = Inmueble::where('comunidad_id', session('comunidad_actual_id'))
            ->orderBy('planta')->orderBy('puerta')
            ->get()
            ->mapWithKeys(function (Inmueble $inmueble) use ($actuales) {
                $actual = $actuales->get($inmueble->id);

                return [$inmueble->id => [
                    'seleccionado' => $actual !== null,
                    'coeficiente'  => $actual?->pivot->coeficiente,
                ]];
            })->all();

        $this->abrir = true;
    }

    public function guardar()
    {
        $grupo = GrupoDeReparto::where('comunidad_id', session('comunidad_actual_id'))->findOrFail($this->grupoId);

        $sync = [];
        foreach ($this->miembros as $inmuebleId => $datos) {
            if (! empty($datos['seleccionado'])) {
                $coeficiente = $datos['coeficiente'] ?? null;
                $sync[$inmuebleId] = ['coeficiente' => $coeficiente !== '' ? $coeficiente : null];
            }
        }

        $grupo->inmuebles()->sync($sync);

        $this->dispatch('toast-success', ['title' => __('Miembros del grupo actualizados')]);
        $this->dispatch('grupo-de-reparto-guardado');
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        $inmuebles = Inmueble::where('comunidad_id', session('comunidad_actual_id'))
            ->orderBy('planta')->orderBy('puerta')
            ->get();

        return view('livewire.grupos-de-reparto.miembros', compact('inmuebles'));
    }
}
