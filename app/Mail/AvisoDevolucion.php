<?php

namespace App\Mail;

use App\Models\Comunidad;
use App\Models\Recibo;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Avisa al propietario de que el banco ha devuelto uno o varios de sus recibos.
 *
 * Va por destinatario, no por recibo: si en la misma tanda se le devuelven dos inmuebles
 * (comparten mandato), recibe un único correo con los dos en vez de dos avisos sueltos.
 */
class AvisoDevolucion extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * Protected, no public: ver la nota en AvisoRemesa.
     *
     * @param  Collection<int, Recibo>  $recibos
     */
    public function __construct(
        protected Collection $recibos,
        protected ?Carbon $fecha,
        protected Comunidad $comunidad,
        ?string $idioma = null,
    ) {
        $this->onQueue('EnviarCorreo');
        $this->locale($idioma ?? config('app.locale'));
    }

    public function asunto(): string
    {
        return __('Aviso de devolución — :comunidad', [
            'comunidad' => $this->comunidad->nombre,
        ]);
    }

    public function build()
    {
        $primero = $this->recibos->first();

        return $this->subject($this->asunto())
            ->view('emails.aviso-devolucion', [
                'nombre'    => $primero?->propietario?->persona?->nombreCompleto,
                'comunidad' => $this->comunidad->nombre,
                'fecha'     => $this->fecha?->format('d/m/Y'),
                'lineas'    => $this->recibos->map(fn (Recibo $recibo) => [
                    'concepto' => $this->concepto($recibo),
                    'importe'  => number_format((float) $recibo->importe, 2, ',', '.').' €',
                ])->all(),
                // El total incluye los gastos bancarios ya repercutidos a estos recibos,
                // no solo lo devuelto: es lo que de verdad hay que transferir.
                'importeTotal' => number_format(
                    $this->recibos->sum(fn (Recibo $recibo) => (float) $recibo->importe + (float) $recibo->gastos_devolucion),
                    2,
                    ',',
                    '.'
                ).' €',
                // El IBAN entero: es el de la comunidad y hace falta completo para
                // poder hacer la transferencia.
                'iban' => $this->formatearIban($this->comunidad->cuentasBancarias()->first()?->iban),
            ]);
    }

    /** El IBAN en grupos de 4, como se ve normalmente, para que sea más fácil de leer y copiar. */
    private function formatearIban(?string $iban): ?string
    {
        return $iban ? trim(chunk_split($iban, 4, ' ')) : null;
    }

    private function concepto(Recibo $recibo): string
    {
        $inmueble = $recibo->inmueble;
        $piso     = $inmueble ? trim(($inmueble->planta ?? '').' '.($inmueble->puerta ?? '')) : '';

        return implode(' — ', array_filter([
            $piso,
            $recibo->presupuesto?->nombre,
            trim(__('Cuota :numero de :anho', [
                'numero' => $recibo->numero_pago,
                'anho'   => $recibo->presupuesto?->anho,
            ])),
        ]));
    }
}
