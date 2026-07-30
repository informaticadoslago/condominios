<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Correo que se envía al "Activar" un usuario en estado Inicial: le pide confirmar
 * que ese correo es el suyo y que se está usando en esta escuela. El enlace, al
 * pincharlo, marca email_verified_at (ver routes/web.php, ruta firmada, sin login).
 */
class ConfirmacionCorreoUsuario extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public User $usuario)
    {
        $this->onQueue('EnviarCorreo');
    }

    public function build()
    {
        $enlace = URL::signedRoute('usuarios.confirmar-correo', ['usuario' => $this->usuario->id]);

        return $this->subject(__('Confirma tu correo — :escuela', ['escuela' => config('mail.from.name')]))
            ->view('emails.confirmacion-correo', [
                'nombre' => $this->usuario->nombreCompleto,
                'escuela' => config('mail.from.name'),
                'enlace' => $enlace,
            ]);
    }
}
