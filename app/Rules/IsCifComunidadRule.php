<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Rules\Includes\ValidadorDocumentoId;
use App\Models\Persona;

/**
 * CIF de comunidad de propietarios: además de ser un CIF válido, debe empezar
 * por la letra H (tabla de tipos de la AEAT). Regla aparte de IsCifRule porque
 * mañana puede haber otros titulares con CIF (proveedores, empresas) que no
 * tengan por qué llevar esa letra.
 */
class IsCifComunidadRule implements Rule {

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value) {
        $validador = new ValidadorDocumentoId();

        // Un documento de una persona 'invisible' se rechaza igual que uno mal formado.
        if (Persona::where('documento_identificativo', $value)->where('invisible', true)->exists()) {
            return false;
        }

        if (! $validador->isValidCIF($value)) {
            return false;
        }

        return strtoupper(substr((string) $value, 0, 1)) === 'H';
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message() {
        return 'El :attribute debe ser un CIF de comunidad de propietarios válido (empieza por H).';
    }

}
