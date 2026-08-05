<?php

namespace App\Http\Requests\Contabilidad;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida solo la FORMA del JSON: tipos, presencia y longitudes.
 *
 * Las reglas contables de verdad —el cuadre, el ejercicio abierto, que la línea lleve
 * cuenta o tercero pero no ambos— están en RegistrarAsientoService, porque quien llama
 * al servicio desde dentro de la aplicación no pasa por aquí y tiene que encontrarse
 * exactamente las mismas comprobaciones.
 */
class RegistrarAsientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** El id de la referencia externa puede llegar como número; para la contabilidad siempre es texto. */
    protected function prepareForValidation(): void
    {
        if ($this->has('referencia.id')) {
            $this->merge([
                'referencia' => array_merge($this->input('referencia'), [
                    'id' => (string) $this->input('referencia.id'),
                ]),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'empresa_contable_id' => ['required', 'integer', 'exists:empresas_contables,id'],
            'ejercicio'           => ['required', 'string', 'max:50'],
            'fecha'               => ['required', 'date_format:Y-m-d'],
            'diario'              => ['nullable', 'string', 'max:10'],
            'concepto'            => ['required', 'string', 'max:255'],

            'referencia'          => ['nullable', 'array'],
            'referencia.tipo'     => ['required_with:referencia', 'string', 'max:50'],
            'referencia.id'       => ['required_with:referencia', 'string', 'max:100'],
            'referencia.evento'   => ['required_with:referencia', 'string', 'max:50'],

            // Autoriza a crear en el acto los terceros que no existan. Solo para cargas
            // iniciales e importaciones: en el uso normal, un tercero desconocido es un
            // error del que llama, y se prefiere el 422 a ensuciar el plan de cuentas.
            'crear_terceros_desconocidos' => ['nullable', 'boolean'],

            'lineas'                        => ['required', 'array', 'min:2'],
            'lineas.*.debe'                 => ['nullable', 'integer'],
            'lineas.*.haber'                => ['nullable', 'integer'],
            'lineas.*.concepto'             => ['nullable', 'string', 'max:255'],
            'lineas.*.cuenta'               => ['nullable', 'string', 'max:8'],
            'lineas.*.tercero'              => ['nullable', 'array'],
            'lineas.*.tercero.tipo'         => ['required_with:lineas.*.tercero', 'string', 'max:50'],
            'lineas.*.tercero.id'           => ['required_with:lineas.*.tercero', 'string', 'max:100'],
            'lineas.*.tercero.clase'        => ['nullable', 'string', 'max:20'],
            'lineas.*.tercero.nif'          => ['nullable', 'string', 'max:20'],
            'lineas.*.tercero.razon_social' => ['nullable', 'string', 'max:150'],
        ];
    }
}
