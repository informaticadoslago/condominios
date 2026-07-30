<?php

/* 
 * Programado por Alberto Lago, ALAGORO 2019
 * Código libre, PERO NO GRATUÍTO.
 */

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

/**
 * Valida falso si es vacío 
 * y el valor del parámetro NO es vacío.
 * 
 * Valida true si tiene valor o
 * si no tiene valor y el parametro pasado está vacío.
 * 
 * Vacío -> empty() valida true.
 */
class RequiredUnless implements Rule {

private $valorAComparar;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($valor='')
    {
        $this->valorAComparar = $valor;        
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value) {
        dump(empty($value) ? 'vacio' : 'no vacio');
        // dd(empty($this->valorAComparar) ? '<----unless' : 'false');
    return empty($value) ? $this->valorAComparar : !$this->valorAComparar;
        
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message() {
        return ' :attribute no tiene formato correcto.';
    }

}
