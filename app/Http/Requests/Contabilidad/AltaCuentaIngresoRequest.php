<?php

namespace App\Http\Requests\Contabilidad;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta de la cuenta de ingresos de un presupuesto o de una derrama.
 *
 * Mismo control de acceso que el alta de terceros: la empresa contable viaja en el
 * cuerpo, así que hay que comprobar que quien pregunta tiene acceso a ella.
 */
class AltaCuentaIngresoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->puedeEscribirEnEmpresaContable(
            (int) $this->input('empresa_contable_id')
        ) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('sujeto.id')) {
            $this->merge([
                'sujeto' => array_merge($this->input('sujeto'), [
                    'id' => (string) $this->input('sujeto.id'),
                ]),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'empresa_contable_id' => ['required', 'integer', 'exists:empresas_contables,id'],
            // Vocabulario contable: 'cuotas' o 'derramas' (ver tipo_ingreso_contables).
            'clase'               => ['required', 'string', 'max:20'],
            // Lo que se leerá en el mayor: «Presupuesto 2026», «Derrama grietas».
            'nombre'              => ['required', 'string', 'max:150'],
            'sujeto'              => ['required', 'array'],
            'sujeto.tipo'         => ['required', 'string', 'max:50'],
            'sujeto.id'           => ['required', 'string', 'max:100'],
        ];
    }
}
