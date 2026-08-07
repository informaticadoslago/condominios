<?php

namespace App\Http\Requests\Contabilidad;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta de un tercero (un propietario que se hace cliente, un proveedor…).
 *
 * Aquí sí se comprueba el acceso, y no solo que el token sea válido: la empresa contable
 * llega en el cuerpo, así que sin esto cualquier token podría dar de alta —y de paso
 * enterarse de qué existe— en la contabilidad de otro.
 */
class AltaTerceroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->puedeEscribirEnEmpresaContable(
            (int) $this->input('empresa_contable_id')
        ) ?? false;
    }

    /** El id del sujeto puede llegar como número; para la contabilidad siempre es texto. */
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
            // Vocabulario contable: de qué grupo cuelga la subcuenta (cliente → 4300).
            'clase'               => ['required', 'string', 'max:20'],
            'nif'                 => ['required', 'string', 'max:20'],
            'razon_social'        => ['required', 'string', 'max:150'],
            // Etiqueta opaca de quien llama; con ella vuelve a pedir el mismo tercero.
            'sujeto'              => ['required', 'array'],
            'sujeto.tipo'         => ['required', 'string', 'max:50'],
            'sujeto.id'           => ['required', 'string', 'max:100'],
        ];
    }
}
