<?php

namespace App\Http\Controllers;

use App\Models\Contacto;
use App\Models\TipoContacto;

/**
 * Destino del enlace firmado de VerificacionCorreoPropietario: marca ese contacto como
 * verificado. Sin login: quien recibe el correo no tiene por qué ser usuario de la
 * aplicación, y la firma temporal de la URL es la única protección.
 */
class ConfirmarCorreoPropietarioController extends Controller
{
    public function __invoke(Contacto $contacto)
    {
        // La firma solo garantiza que el enlace lo hicimos nosotros, no que apunte a un
        // correo: sin esto, un enlace manipulado marcaría como verificado un teléfono.
        abort_unless($contacto->tipo_contacto_id == TipoContacto::EMAIL, 404);

        if (! $contacto->verified_at) {
            $contacto->forceFill(['verified_at' => now()])->save();
        }

        return view('emails.correo-propietario-confirmado', [
            'correo' => $contacto->valor,
        ]);
    }
}
