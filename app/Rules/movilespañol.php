<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class movilespañol implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $resultado = (strlen($value)=== config('defines.len_movilespañol'))
                  && in_array(substr($value,0,1), ['6','7']);
        return $resultado;         
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'No es un móvil español';
    }
}
