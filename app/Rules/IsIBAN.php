<?php

/*
 * Programado por Alberto Lago, ALAGORO 2019
 * Código libre, PERO NO GRATUÍTO.
 */

namespace App\Rules;

use App\Rules\Includes\CuentasBancariasInclude;
use Illuminate\Contracts\Validation\Rule;

class IsIBAN implements Rule
{

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        // Si el campo viene null o vacío, no falla (lo controlará 'required' o 'required_if')
        if (is_null($value) || $value === '') {
            return true;
        }
        $validador = new CuentasBancariasInclude();
        return $validador->comprobar_iban($value);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return ' :attribute no tiene formato correcto.';
    }

}
