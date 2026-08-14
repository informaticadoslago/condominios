<?php

namespace App\Http\Requests\Contabilidad;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta de un proyecto contable (la dimensión analítica de una actividad).
 *
 * Mismo control de acceso que el resto de la frontera: la empresa contable viaja en el
 * cuerpo, así que hay que comprobar que quien pregunta tiene acceso a ella.
 */
class AltaProyectoContableRequest extends FormRequest
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
            'nombre'              => ['required', 'string', 'max:150'],
            'sujeto'              => ['required', 'array'],
            'sujeto.tipo'         => ['required', 'string', 'max:50'],
            'sujeto.id'           => ['required', 'string', 'max:100'],
        ];
    }
}
