<?php

namespace App\Http\Requests\Contabilidad;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida solo la FORMA del JSON. Las reglas de verdad —qué cuenta como el mismo
 * ejercicio, qué pasa si ya existe— están en AbrirEjercicioContableService, porque quien
 * lo llama desde dentro de la aplicación no pasa por aquí.
 */
class AbrirEjercicioContableRequest extends FormRequest
{
    /** Mismo control que el resto: la empresa llega en el cuerpo, hay que comprobarla. */
    public function authorize(): bool
    {
        return $this->user()?->puedeEscribirEnEmpresaContable(
            (int) $this->input('empresa_contable_id')
        ) ?? false;
    }

    public function rules(): array
    {
        return [
            'empresa_contable_id' => ['required', 'integer', 'exists:empresas_contables,id'],
            'nombre'              => ['required', 'string', 'max:50'],
            'fecha_inicio'        => ['required', 'date_format:Y-m-d'],
            'fecha_fin'           => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
        ];
    }
}
