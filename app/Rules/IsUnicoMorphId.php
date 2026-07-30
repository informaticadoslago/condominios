<?php
namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class IsUnicoMorphId implements ValidationRule
{

    private $tabla, $modelo, $columna, $modeloId;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($tabla, $modelo, $columna, $modeloId)
    {
        $this->tabla   = $tabla;
        $this->modelo   = $modelo;
        $this->columna = $columna;
        $this->modeloId = $modeloId;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        //$tabla::where($columna, $modelo)   ????
    }
}
