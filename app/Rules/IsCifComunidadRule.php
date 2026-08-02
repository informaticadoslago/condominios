<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Rules\Includes\ValidadorDocumentoId;
use App\Models\Persona;

/**
 * CIF de comunidad de propietarios. Exige CIF (o NIF, ver nota TEMPORAL más abajo)
 * válido; el requisito de que empiece por H (tabla de tipos de la AEAT) está
 * desactivado hasta nuevo aviso, ver comentario dentro de passes(). Regla aparte
 * de IsCifRule porque mañana puede haber otros titulares con CIF (proveedores,
 * empresas) que no tengan por qué llevar esa letra.
 */
class IsCifComunidadRule implements Rule {

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value) {
        $validador = new ValidadorDocumentoId();

        // Un documento de una persona 'invisible' se rechaza igual que uno mal formado.
        if (Persona::where('documento_identificativo', $value)->where('invisible', true)->exists()) {
            return false;
        }

        // TEMPORAL: para poder probar la importación de facturas con NIFs personales
        // reales (facturas propias, no de una comunidad), se admite también un NIF
        // válido además del CIF-H habitual. Revertir cuando termine la prueba.
        if ($validador->isValidNIF($value)) {
            return true;
        }

        // DESACTIVADO hasta nuevo aviso (a petición del usuario): exigía que el CIF
        // empezara por H (código AEAT de comunidad de propietarios). Reactivar
        // descomentando la línea de abajo cuando se confirme que hace falta de nuevo.
        // return strtoupper(substr((string) $value, 0, 1)) === 'H';

        return $validador->isValidCIF($value);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message() {
        return 'El :attribute debe ser un CIF de comunidad de propietarios válido.';
    }

}
