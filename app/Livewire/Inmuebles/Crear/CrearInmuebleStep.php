<?php

namespace App\Livewire\Inmuebles\Crear;

use App\Models\Borrador;
use Spatie\LivewireWizard\Components\StepComponent;

/**
 * Paso base del wizard de inmueble: navegación común (siguiente/anterior/saltar por
 * cabecera a un paso YA visitado) y salida. Nunca se puede saltar hacia adelante por la
 * cabecera (los pasos futuros no son clicables): solo "Siguiente" avanza, así que un
 * paso intermedio siempre se revalida al pasar por él. En alta nueva, nada es real
 * hasta "Terminar" — mientras tanto vive en el payload de un Borrador (ver DatosStep y
 * PropietariosStep).
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

    /** Normal: deja el borrador vivo (se puede retomar). Con Shift: lo borra también. */
    public function salir($borrarBorrador = false)
    {
        if ($borrarBorrador) {
            $borradorId = session('inmueble_borrador_id');
            if ($borradorId) {
                Borrador::delUsuario()->deTipo(Borrador::TIPO_INMUEBLE)->whereKey($borradorId)->delete();
            }
            session()->forget('inmueble_borrador_id');
        }

        return $this->redirect(route('inmuebles.index'), navigate: true);
    }
}
