<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Rules\Includes\ValidadorDocumentoId;

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
