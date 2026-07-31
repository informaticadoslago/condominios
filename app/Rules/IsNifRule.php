<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Rules\Includes\ValidadorDocumentoId;
use App\Models\Persona;

class IsNifRule implements Rule {


    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value) {
        $validador = new ValidadorDocumentoId();

        // Un documento de una persona 'invisible' (p. ej. el superadmin) se rechaza
        // igual que uno mal formado: no hay pista de que exista.
        if (Persona::where('documento_identificativo', $value)->where('invisible', true)->exists()) {
            return false;
        }

        return $validador->isValidNIF($value);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message() {
        return 'No tiene formato :attribute correcto.';
    }

}
