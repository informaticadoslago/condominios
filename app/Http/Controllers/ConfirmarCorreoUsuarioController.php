<?php

namespace App\Http\Controllers;

use App\Models\User;

/**
 * Destino del enlace firmado del correo de "Activar" (ConfirmacionCorreoUsuario): marca
 * el correo del usuario como verificado. Sin login: quien recibe el correo puede no
 * tener sesión iniciada todavía; la firma de la URL es la única protección.
 */
class ConfirmarCorreoUsuarioController extends Controller
{
    public function __invoke(User $usuario)
    {
        if (! $usuario->email_verified_at) {
            $usuario->forceFill(['email_verified_at' => now()])->save();
        }

        return view('auth.correo-confirmado');
    }
}
