<?php

namespace App\Livewire\AdministracionSistema\PruebasCorreo;

use App\Mail\AvisoDevolucion;
use App\Mail\AvisoRemesa;
use App\Mail\AvisoTransferencia;
use App\Mail\ConfirmacionCorreoUsuario;
use App\Mail\VerificacionCorreoPropietario;
use App\Models\Comunidad;
use App\Models\Contacto;
use App\Models\LineaRemesa;
use App\Models\Recibo;
use App\Models\TipoContacto;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

/**
 * Manda cada plantilla de correo con el primer dato real que encuentre (recibo, línea
 * de remesa, contacto...), a la dirección que se indique, para poder ver a simple vista
 * el aspecto y el pie de cada una. No pasa por EnviarAvisosRecibos ni por
 * EnviarVerificacionCorreo, así que no deja rastro en `correos_enviados`: es una prueba,
 * no un envío real.
 */
class Formulario extends Component
{
    public string $destinatario = '';

    public function mount(): void
    {
        $this->destinatario = auth()->user()->email ?? '';
    }

    public function enviarAvisoRemesa(): void
    {
        $this->validarDestinatario();

        $linea = LineaRemesa::first();

        if (! $linea) {
            $this->avisarSinDatos();

            return;
        }

        $this->enviar(new AvisoRemesa($linea));
    }

    public function enviarAvisoTransferencia(): void
    {
        $this->validarDestinatario();

        $recibo    = Recibo::first();
        $comunidad = Comunidad::first();

        if (! $recibo || ! $comunidad) {
            $this->avisarSinDatos();

            return;
        }

        $this->enviar(new AvisoTransferencia($recibo, $comunidad));
    }

    public function enviarAvisoDevolucion(): void
    {
        $this->validarDestinatario();

        $recibos   = Recibo::limit(2)->get();
        $comunidad = Comunidad::first();

        if ($recibos->isEmpty() || ! $comunidad) {
            $this->avisarSinDatos();

            return;
        }

        $this->enviar(new AvisoDevolucion($recibos, now(), $comunidad));
    }

    public function enviarVerificacionPropietario(): void
    {
        $this->validarDestinatario();

        $contacto  = Contacto::where('tipo_contacto_id', TipoContacto::EMAIL)->first();
        $comunidad = Comunidad::first();

        if (! $contacto || ! $comunidad) {
            $this->avisarSinDatos();

            return;
        }

        $this->enviar(new VerificacionCorreoPropietario($contacto, $comunidad));
    }

    public function enviarConfirmacionUsuario(): void
    {
        $this->validarDestinatario();

        $usuario = User::first();

        if (! $usuario) {
            $this->avisarSinDatos();

            return;
        }

        $this->enviar(new ConfirmacionCorreoUsuario($usuario));
    }

    private function validarDestinatario(): void
    {
        $this->validate([
            'destinatario' => ['required', 'email'],
        ]);
    }

    private function avisarSinDatos(): void
    {
        $this->dispatch('toast-error', ['title' => __('No hay datos de ejemplo en la base de datos para generar este correo')]);
    }

    /** Manda de verdad, sin cola: es una prueba, se quiere ver ya. */
    private function enviar(Mailable $mailable): void
    {
        Mail::to($this->destinatario)->send($mailable);

        $this->dispatch('toast-success', ['title' => __('Correo de prueba enviado')]);
    }

    public function render()
    {
        return view('livewire.administracion-sistema.pruebas-correo.formulario');
    }
}
