<?php

namespace App\Http\Requests\Contabilidad;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida solo la FORMA del JSON. Las reglas de verdad —qué se normaliza y qué cuenta
 * como la misma empresa— están en ResolverEmpresaContableService, porque quien lo llama
 * desde dentro de la aplicación no pasa por aquí.
 */
class ResolverEmpresaContableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cif'          => ['required', 'string', 'max:20'],
            'razon_social' => ['required', 'string', 'max:150'],
        ];
    }
}
