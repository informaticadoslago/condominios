<?php

namespace App\Livewire\Inmuebles\Crear;

use App\Livewire\Inmuebles\Crear\Steps\DatosStep;
use App\Livewire\Inmuebles\Crear\Steps\PropietariosStep;
use App\Models\Inmueble;
use Spatie\LivewireWizard\Components\WizardComponent;

/**
 * Wizard de alta/edición de inmueble: datos propios y propietarios. A diferencia
 * del alta de alumno, aquí no hay borrador ni JSON intermedio — el inmueble se
 * crea de verdad al terminar el primer paso, así que el segundo ya trabaja sobre
 * un registro real.
 */
class CrearInmueble extends WizardComponent
{
    public const PASO_DATOS        = 'inmuebles.crear.steps.datos-step';
    public const PASO_PROPIETARIOS = 'inmuebles.crear.steps.propietarios-step';

    public ?int $inmuebleId = null;

    public function mount(?int $inmuebleId = null)
    {
        $this->inmuebleId = $inmuebleId;
    }

    public function steps(): array
    {
        return [
            DatosStep::class,
            PropietariosStep::class,
        ];
    }

    public function initialState(): ?array
    {
        $semilla = $this->stepNames()
            ->mapWithKeys(fn (string $paso) => [$paso => ['inmuebleId' => $this->inmuebleId]])
            ->toArray();

        if ($this->inmuebleId && ($inmueble = Inmueble::find($this->inmuebleId))) {
            $semilla[self::PASO_DATOS] = array_merge($semilla[self::PASO_DATOS], [
                'comunidad_id'         => $inmueble->comunidad_id,
                'ocupacion_id'         => $inmueble->ocupacion_id,
                'tipo_inmueble_id'     => $inmueble->tipo_inmueble_id,
                'planta'               => $inmueble->planta,
                'puerta'               => $inmueble->puerta,
                'coeficiente'          => $inmueble->coeficiente,
                'referencia_catastral' => $inmueble->referencia_catastral,
            ]);
        }

        return $semilla;
    }
}
