<?php

namespace App\Livewire\Propietarios\Crear;

use App\Models\Borrador;
use Spatie\LivewireWizard\Components\StepComponent;

/**
 * Paso base del wizard de propietario: navegación común (siguiente/anterior/salir por
 * cabecera). En alta nueva, nada es real hasta "Terminar" — mientras tanto vive en el
 * payload de un Borrador (ver los Steps). Calcado de Inmuebles\Crear\CrearInmuebleStep.
 */
abstract class CrearPropietarioStep extends StepComponent
{
    // Cuando el wizard se abre embebido en un modal (p.ej. desde el alta de un
    // inmueble), "Salir"/"Terminar" no navegan a una página nueva: disparan un
    // evento para que el componente padre cierre el modal (ver salir() y
    // CuentaBancariaStep::terminar()).
    public bool $embebido = false;

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

    /**
     * Clave de sesión del borrador. Distinta cuando el wizard está embebido en un
     * modal, para no chocar con un borrador de propietario ya abierto a página
     * completa (o viceversa).
     */
    protected function claveBorrador(): string
    {
        return $this->embebido ? 'propietario_borrador_id_modal' : 'propietario_borrador_id';
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
            $borradorId = session($this->claveBorrador());
            if ($borradorId) {
                Borrador::delUsuario()->deTipo(Borrador::TIPO_PROPIETARIO)->whereKey($borradorId)->delete();
            }
            session()->forget($this->claveBorrador());
        }

        if ($this->embebido) {
            $this->dispatch('cerrar-modal-propietario');

            return;
        }

        return $this->redirect(route('propietarios.index'), navigate: true);
    }
}
