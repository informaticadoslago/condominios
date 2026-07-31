<?php

namespace App\Livewire\Inmuebles\Crear;

use Spatie\LivewireWizard\Components\StepComponent;

/**
 * Paso base del wizard de inmueble: navegación común (siguiente/anterior/saltar por
 * cabecera) y salida. A diferencia del alta de alumno, aquí no hay borrador: el
 * inmueble se crea nada más terminar el primer paso, así que no hace falta.
 */
abstract class CrearInmuebleStep extends StepComponent
{
    public function messages()
    {
        return [
            'required' => 'Debe rellenar :attribute',
            'max'      => 'Máxima longitud de :attribute = :max',
        ];
    }

    /** Validación/persistencia propia de cada paso. Por defecto, nada. */
    protected function validarYGuardar(): void
    {
    }

    public function submit()
    {
        $this->validarYGuardar();
        $this->nextStep();
    }

    public function stepBack()
    {
        $this->previousStep();
    }

    public function mostrarDesdeCabecera($paso)
    {
        $this->showStep($paso);
    }

    public function salir()
    {
        return $this->redirect(route('inmuebles.index'), navigate: true);
    }
}
