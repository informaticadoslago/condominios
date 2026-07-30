<?php

/* 
 * Programado por Alberto Lago, ALAGORO 2024
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
class ProhibidoSi implements Rule {

private $valorAComparar;
private $elseRequired;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($valorCampo, $elseRequired = false)
    {
        $this->elseRequired = $elseRequired;
        $this->valorAComparar = $valorCampo;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value) {
        //dump($this->valorAComparar.'<----$this->valorAComparar');
        //dd($this->elseRequired);
        $validador = $this->valorAComparar ? empty($value) : (!$this->elseRequired ? true : !empty($value));
        return $validador;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message() {
        return ' :attribute no es correcto.';
    }

}
