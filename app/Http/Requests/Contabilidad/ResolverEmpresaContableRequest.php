<?php

namespace App\Http\Requests\Contabilidad;

use App\Support\HabilidadToken;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida solo la FORMA del JSON. Las reglas de verdad —qué se normaliza y qué cuenta
 * como la misma empresa— están en ResolverEmpresaContableService, porque quien lo llama
 * desde dentro de la aplicación no pasa por aquí.
 */
class ResolverEmpresaContableRequest extends FormRequest
{
    /**
     * Aquí no hay empresa que comprobar —se está creando—, así que lo único que se exige
     * es que el token pueda escribir. Quién puede crear empresas contables sigue sin
     * decidirse (ver docs/pendientes.md).
     */
    public function authorize(): bool
    {
        return $this->user()?->tokenCan(HabilidadToken::ESCRIBIR) ?? false;
    }

    public function rules(): array
    {
        return [
            'cif'          => ['required', 'string', 'max:20'],
            'razon_social' => ['required', 'string', 'max:150'],
        ];
    }
}
