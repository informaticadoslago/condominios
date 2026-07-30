<?php

namespace App\Rules;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\Rule;

/**
 * La fecha de nacimiento corresponde a una persona mayor de edad
 * (según config('defines.edad.mayordeedad')). Autocontenida con Carbon
 * (no depende de helpers globales).
 */
class IsMayorEdad implements Rule
{
    public function passes($attribute, $value): bool
    {
        if (blank($value)) {
            return false;
        }

        return Carbon::parse($value)->age >= config('defines.edad.mayordeedad', 18);
    }

    public function message(): string
    {
        return __(':attribute no es mayor de edad (:edad).', ['edad' => config('defines.edad.mayordeedad', 18)]);
    }
}
