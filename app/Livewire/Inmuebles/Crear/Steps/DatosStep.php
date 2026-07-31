<?php

namespace App\Livewire\Inmuebles\Crear\Steps;

use App\Livewire\Inmuebles\Crear\CrearInmuebleStep;
use App\Models\Comunidad;
use App\Models\Inmueble;
use App\Models\TipoInmueble;
use App\Models\TipoOcupacion;

class DatosStep extends CrearInmuebleStep
{
    // Alta: se rellena al terminar este paso (se crea el inmueble). Edición: ya viene puesto.
    public ?int $inmuebleId = null;

    public $comunidad_id;
    public $ocupacion_id;
    public $tipo_inmueble_id;
    public $planta;
    public $puerta = '';
    public $coeficiente;
    public $referencia_catastral;

    public function stepInfo(): array
    {
        return ['label' => __('Datos del inmueble')];
    }

    /** Admite coma o punto como separador decimal: se normaliza a punto antes de validar. */
    public function updatedCoeficiente($value)
    {
        $this->coeficiente = str_replace(',', '.', $value);
    }

    protected function rules()
    {
        return [
            'comunidad_id'         => ['required', 'exists:comunidades,id'],
            'ocupacion_id'         => ['required', 'exists:tipo_ocupaciones,id'],
            'tipo_inmueble_id'     => ['required', 'exists:tipo_inmuebles,id'],
            'planta'               => ['required', 'integer', 'between:-99,99'],
            'puerta'               => ['nullable', 'string', 'max:5'],
            'coeficiente'          => ['required', 'numeric', 'min:0.01', 'max:100', 'regex:/^\d{1,3}(\.\d{1,2})?$/'],
            'referencia_catastral' => ['nullable', 'string', 'max:20'],
        ];
    }

    protected function validarYGuardar(): void
    {
        $data = $this->validate();

        if ($this->inmuebleId) {
            Inmueble::whereKey($this->inmuebleId)->update($data);
        } else {
            $inmueble          = Inmueble::create($data);
            $this->inmuebleId  = $inmueble->id;
        }
    }

    public function render()
    {
        return view('livewire.inmuebles.crear.steps.datos-step', [
            'comunidades'   => Comunidad::orderBy('id')->get(),
            'ocupaciones'   => TipoOcupacion::orderBy('descripcion')->get(),
            'tiposInmueble' => TipoInmueble::orderBy('descripcion')->get(),
        ]);
    }
}
