<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Telefono implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Si el valor está vacío, pasamos la regla
        if (empty($value)) {
            return;
        }
        
        // Teléfono español (con o sin prefijo)
        $regexEspanol = '/^(?:\+34|0034)?[6789]\d{8}$/';

        // Teléfono internacional (otro prefijo distinto de +34 o 0034)
        $regexInternacional = '/^(?:(?:\+(?!34)|00(?!34))\d{6,15})$/';

        if (!preg_match($regexEspanol, $value) && !preg_match($regexInternacional, $value)) {
            $fail("El campo :attribute no tiene un formato válido. Debe ser un número español (9 dígitos, opcional +34/0034) o internacional con prefijo (+XX o 00XX).");
        }
    }
}

