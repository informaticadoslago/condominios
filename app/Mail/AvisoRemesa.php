<?php

namespace App\Mail;

use App\Models\LineaRemesa;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Avisa al propietario de que se le va a cargar un recibo domiciliado.
 *
 * Se construye desde la línea de remesa y no desde el recibo: el importe que se le va a
 * cobrar es el de la línea (el saldo en el momento de remesar), y la fecha de cargo y la
 * cuenta son de ese envío concreto.
 */
class AvisoRemesa extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * Protected, no public: Laravel inyecta en la vista las propiedades públicas DESPUÉS
     * de los datos de view(), y una pública llamada igual que una clave la pisaría.
     */
    public function __construct(
        protected LineaRemesa $linea,
        ?string $idioma = null,
    ) {
        $this->onQueue('EnviarCorreo');
        $this->locale($idioma ?? config('app.locale'));
    }

    public function asunto(): string
    {
        return __('Aviso de cargo — :comunidad', [
            'comunidad' => $this->linea->remesa?->comunidad?->nombre,
        ]);
    }

    public function build()
    {
        $recibo = $this->linea->recibo;

        return $this->subject($this->asunto())
            ->view('emails.aviso-remesa', [
                'nombre'     => $recibo?->propietario?->persona?->nombreCompleto,
                'comunidad'  => $this->linea->remesa?->comunidad?->nombre,
                'importe'    => number_format((float) $this->linea->importe, 2, ',', '.').' €',
                'fechaCargo' => $this->linea->remesa?->fecha_cargo?->format('d/m/Y'),
                // Solo los últimos dígitos: el correo no es sitio para el IBAN entero, y
                // con eso basta para que reconozca su cuenta.
                'cuenta'    => $this->ultimosDigitos($this->linea->iban),
                'inmueble'  => $this->nombreInmueble($recibo),
                'concepto'  => $this->concepto($recibo),
            ]);
    }

    private function ultimosDigitos(?string $iban): string
    {
        return $iban ? '···'.mb_substr($iban, -4) : '';
    }

    private function nombreInmueble($recibo): string
    {
        $inmueble = $recibo?->inmueble;

        if (! $inmueble) {
            return '';
        }

        return trim(($inmueble->planta ?? '').' '.($inmueble->puerta ?? ''));
    }

    private function concepto($recibo): string
    {
        if (! $recibo) {
            return '';
        }

        return __('Cuota :numero de :anho', [
            'numero' => $recibo->numero_pago,
            'anho'   => $recibo->presupuesto?->anho,
        ]);
    }
}
