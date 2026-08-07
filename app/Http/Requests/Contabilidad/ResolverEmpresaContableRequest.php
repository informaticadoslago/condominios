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
    /**
     * Cerrado: por la API no se crean empresas contables. Aquí no hay empresa que
     * comprobar —se está creando—, así que ninguna habilidad puede acotar quién entra,
     * y una empresa contable de más no se borra con un botón.
     *
     * Se dan de alta desde dentro: enlazar la comunidad con la contabilidad
     * (`Comunidades\Lista::enlazarContabilidad()`) llama al servicio directamente.
     */
    public function authorize(): bool
    {
        return false;
    }

    public function rules(): array
    {
        return [
            'cif'          => ['required', 'string', 'max:20'],
            'razon_social' => ['required', 'string', 'max:150'],
        ];
    }
}
