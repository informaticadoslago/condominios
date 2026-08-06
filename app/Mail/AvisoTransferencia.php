<?php

namespace App\Mail;

use App\Models\Comunidad;
use App\Models\Recibo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Avisa al propietario que paga por transferencia de que le toca ingresar.
 *
 * Aquí el importe es el SALDO del recibo, no su importe: si ya pagó algo a cuenta, se le
 * pide lo que falta, no el total otra vez.
 */
class AvisoTransferencia extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /** Protected, no public: ver la nota en AvisoRemesa. */
    public function __construct(
        protected Recibo $recibo,
        protected Comunidad $comunidadDestino,
        ?string $idioma = null,
    ) {
        $this->onQueue('EnviarCorreo');
        $this->locale($idioma ?? config('app.locale'));
    }

    public function asunto(): string
    {
        return __('Recibo pendiente — :comunidad', [
            'comunidad' => $this->comunidadDestino->nombre,
        ]);
    }

    public function build()
    {
        return $this->subject($this->asunto())
            ->view('emails.aviso-transferencia', [
                'nombre'      => $this->recibo->propietario?->persona?->nombreCompleto,
                'comunidad'   => $this->comunidadDestino->nombre,
                'importe'     => number_format((float) $this->recibo->saldo, 2, ',', '.').' €',
                'vencimiento' => $this->recibo->fecha_vencimiento?->format('d/m/Y'),
                'inmueble'    => $this->nombreInmueble(),
                // Aquí sí va el IBAN entero: es el de la comunidad y es lo que necesita
                // para poder hacer la transferencia.
                'iban'     => $this->comunidadDestino->cuentasBancarias()->first()?->iban,
                'concepto' => $this->concepto(),
            ]);
    }

    private function nombreInmueble(): string
    {
        $inmueble = $this->recibo->inmueble;

        if (! $inmueble) {
            return '';
        }

        return trim(($inmueble->planta ?? '').' '.($inmueble->puerta ?? ''));
    }

    /**
     * Lo que tiene que poner en la transferencia. Lleva el inmueble para poder casar el
     * ingreso cuando llegue el extracto: el nombre del ordenante no siempre coincide con
     * el del propietario.
     */
    private function concepto(): string
    {
        return trim(__('Cuota :numero de :anho', [
            'numero' => $this->recibo->numero_pago,
            'anho'   => $this->recibo->presupuesto?->anho,
        ]).' '.$this->nombreInmueble());
    }
}
