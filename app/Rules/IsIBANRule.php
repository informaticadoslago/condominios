<?php

namespace App\Rules;

use App\Http\Traits\ValidatesIbanSepa;
use Illuminate\Contracts\Validation\Rule;

class IsIBANRule implements Rule
{
    use ValidatesIbanSepa;

    /**
     * Determine if the validation rule passes.
     */
    public function passes($attribute, $value)
    {
        return $this->validarIbanSepa($value);
    }

    /**
     * Get the validation error message.
     */
    public function message()
    {
        return ':attribute no tiene formato correcto.';
    }
}
