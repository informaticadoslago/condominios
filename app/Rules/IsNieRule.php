<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Rules\Includes\ValidadorDocumentoId;

class IsNieRule implements Rule {

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct() {
        
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value) {
        $validador = new ValidadorDocumentoId();

        return $validador->isValidNIE($value);
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
