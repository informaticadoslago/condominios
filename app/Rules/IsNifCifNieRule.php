<?php

namespace App\Rules;

use App\Models\Persona;
use App\Rules\Includes\ValidadorDocumentoId;
use Illuminate\Contracts\Validation\Rule;

/**
 * Documento identificativo español válido, sea NIF, NIE o CIF: para entidades que
 * pueden ser persona física o jurídica (p. ej. Sociedad), a diferencia de
 * IsCifRule/IsCifComunidadRule que asumen persona jurídica.
 */
class IsNifCifNieRule implements Rule
{
    public function passes($attribute, $value)
    {
        if (Persona::where('documento_identificativo', $value)->where('invisible', true)->exists()) {
            return false;
        }

        return (new ValidadorDocumentoId())->isValidIdNumber($value);
    }

    public function message()
    {
        return 'El :attribute debe ser un NIF, NIE o CIF válido.';
    }
}
