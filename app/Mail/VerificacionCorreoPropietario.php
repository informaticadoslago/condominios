<?php

namespace App\Mail;

use App\Models\Comunidad;
use App\Models\Contacto;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Pide a un propietario que confirme su dirección de correo. El enlace es una URL
 * firmada que, al pincharla, marca `verified_at` en ese contacto (ver
 * ConfirmarCorreoPropietarioController); no hace falta tener sesión.
 *
 * El idioma se pasa al construirlo y se aplica con locale(): Laravel traduce entonces
 * todos los __() de la plantilla y del asunto. Hoy no hay idioma por persona, así que
 * quien envía decide; el día que lo haya, se pasa aquí y no cambia nada más.
 */
class VerificacionCorreoPropietario extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * Las dos van protected a propósito: Laravel inyecta en la vista las propiedades
     * PÚBLICAS de un Mailable y lo hace DESPUÉS de los datos de view(), así que una
     * propiedad pública $comunidad pisaría el 'comunidad' => nombre que se pasa abajo y
     * la plantilla acabaría imprimiendo el modelo entero.
     */
    public function __construct(
        protected Contacto $contacto,
        protected Comunidad $comunidad,
        ?string $idioma = null,
    ) {
        $this->onQueue('EnviarCorreo');
        $this->locale($idioma ?? config('app.locale'));
    }

    public function asunto(): string
    {
        return __('Confirma tu correo — :comunidad', ['comunidad' => $this->comunidad->nombre]);
    }

    public function build()
    {
        // Caduca a propósito: un enlace de verificación que vale para siempre acaba
        // circulando en un buzón ajeno o en un reenvío.
        $enlace = URL::temporarySignedRoute(
            'propietarios.confirmar-correo',
            now()->addDays(30),
            ['contacto' => $this->contacto->id],
        );

        return $this->subject($this->asunto())
            ->view('emails.verificacion-correo-propietario', [
                'nombre'    => $this->contacto->contactable?->nombreCompleto,
                'comunidad' => $this->comunidad->nombre,
                'enlace'    => $enlace,
                'correoContacto' => $this->comunidad->correo_contacto,
            ]);
    }
}
