<?php

namespace App\Livewire\Inmuebles\Crear\Steps;

use App\Livewire\Inmuebles\Crear\CrearInmuebleStep;
use App\Models\Borrador;
use App\Models\Comunidad;
use App\Models\Inmueble;
use App\Models\TipoInmueble;
use App\Models\TipoOcupacion;
use Livewire\Attributes\Locked;

class DatosStep extends CrearInmuebleStep
{
    // Alta: null durante todo el wizard (nada es real hasta "Terminar"). Edición: ya
    // viene puesto desde la ruta, y aquí sí se trabaja sobre el registro real.
    #[Locked]
    public ?int $inmuebleId = null;

    // Fijada por sesión (ver Inmuebles\Formulario), nunca por el cliente: #[Locked]
    // rechaza cualquier intento de cambiarla desde el navegador (consola, DevTools...).
    #[Locked]
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

    /**
     * El wizard solo siembra el estado inicial una vez, en el primer paso; si este
     * step se abre ya con inmuebleId pero sin el resto de campos (p.ej. al retomar
     * la EDICIÓN de un inmueble real), se recargan aquí directamente del inmueble
     * en vez de confiar en que el estado haya viajado bien entre pasos.
     */
    public function mount()
    {
        if ($this->inmuebleId && ! $this->comunidad_id) {
            $inmueble = Inmueble::find($this->inmuebleId);

            if ($inmueble) {
                $this->comunidad_id         = $inmueble->comunidad_id;
                $this->ocupacion_id         = $inmueble->ocupacion_id;
                $this->tipo_inmueble_id     = $inmueble->tipo_inmueble_id;
                $this->planta               = $inmueble->planta;
                $this->puerta               = $inmueble->puerta;
                $this->coeficiente          = $inmueble->coeficiente;
                $this->referencia_catastral = $inmueble->referencia_catastral;
            }
        }
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

    /**
     * Alta o edición: nunca se toca `inmuebles` aquí, solo el borrador. Lo real se
     * graba de golpe al pulsar "Terminar" en el paso siguiente (ver PropietariosStep).
     */
    protected function validarYGuardar(): void
    {
        $data = $this->validate();

        if ($this->inmuebleId) {
            // La comunidad de un inmueble ya existente no se toca desde aquí.
            unset($data['comunidad_id']);
        } else {
            $data['comunidad_id'] = session('comunidad_actual_id');
        }

        $borrador = $this->borradorActual();
        $payload  = $borrador?->payload ?? [];
        $payload['inmueble_id'] = $this->inmuebleId;
        $payload['datos']       = $data;

        if ($borrador) {
            $borrador->update(['payload' => $payload]);
        } else {
            $borrador = Borrador::create([
                'user_id' => auth()->id(),
                'tipo'    => Borrador::TIPO_INMUEBLE,
                'payload' => $payload,
            ]);
            session(['inmueble_borrador_id' => $borrador->id]);
        }
    }

    private function borradorActual(): ?Borrador
    {
        $borradorId = session('inmueble_borrador_id');

        return $borradorId ? Borrador::delUsuario()->deTipo(Borrador::TIPO_INMUEBLE)->find($borradorId) : null;
    }

    public function render()
    {
        return view('livewire.inmuebles.crear.steps.datos-step', [
            'comunidadActual' => Comunidad::find($this->comunidad_id),
            'ocupaciones'     => TipoOcupacion::orderBy('descripcion')->get(),
            'tiposInmueble'   => TipoInmueble::orderBy('descripcion')->get(),
        ]);
    }
}
