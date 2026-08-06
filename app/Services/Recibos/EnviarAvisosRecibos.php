<?php

namespace App\Services\Recibos;

use App\Mail\AvisoRemesa;
use App\Mail\AvisoTransferencia;
use App\Models\AvisoRecibo;
use App\Models\Comunidad;
use App\Models\Contacto;
use App\Models\Estado;
use App\Models\LineaRemesa;
use App\Models\Recibo;
use App\Models\Remesa;
use App\Models\TipoContacto;
use Illuminate\Support\Facades\Mail;

/**
 * Manda los avisos de recibo y deja constancia de cada uno.
 *
 * El rastro se escribe siempre que se encola un correo, no cuando el servidor lo
 * entrega: lo que interesa registrar es que desde aquí se le avisó, en qué fecha y por
 * qué motivo. Si el correo rebota después, eso es cosa de la cola.
 *
 * A quien no tiene dirección no se le puede avisar y se cuenta aparte, para poder
 * decirlo en pantalla en vez de dar por bueno un envío que no ha ido a ninguna parte.
 */
final class EnviarAvisosRecibos
{
    /**
     * Avisa a los propietarios incluidos en una remesa del cargo que se les va a pasar.
     *
     * @return array{avisados: int, sin_correo: int}
     */
    public function deRemesa(Remesa $remesa, ?string $idioma = null): array
    {
        $lineas = $remesa->lineas()
            ->whereNull('fecha_devolucion')
            ->with(['recibo.propietario.persona.contactos', 'recibo.inmueble', 'recibo.presupuesto'])
            ->get();

        $avisados = 0;
        $sinCorreo = 0;

        foreach ($lineas as $linea) {
            $correo = $this->correoDe($linea->recibo);

            if (! $correo) {
                $sinCorreo++;

                continue;
            }

            $mailable = new AvisoRemesa($linea, $idioma);

            Mail::to($correo->valor)->queue($mailable);
            $this->registrar($linea->recibo, $mailable->asunto(), $correo->valor);

            $avisados++;
        }

        return ['avisados' => $avisados, 'sin_correo' => $sinCorreo];
    }

    /**
     * Avisa de los recibos indicados a quien paga por transferencia. Los recibos llegan
     * ya elegidos desde la pantalla (se pueden desmarcar), así que aquí no se filtra por
     * vencimiento: solo se comprueba que sigan debiendo algo.
     *
     * @param  array<int>  $reciboIds
     * @return array{avisados: int, sin_correo: int}
     */
    public function deTransferencia(array $reciboIds, Comunidad $comunidad, ?string $idioma = null): array
    {
        $recibos = Recibo::whereIn('id', $reciboIds)
            ->where('saldo', '>', 0)
            ->with(['propietario.persona.contactos', 'inmueble', 'presupuesto'])
            ->get();

        $avisados  = 0;
        $sinCorreo = 0;

        foreach ($recibos as $recibo) {
            $correo = $this->correoDe($recibo);

            if (! $correo) {
                $sinCorreo++;

                continue;
            }

            $mailable = new AvisoTransferencia($recibo, $comunidad, $idioma);

            Mail::to($correo->valor)->queue($mailable);
            $this->registrar($recibo, $mailable->asunto(), $correo->valor);

            $avisados++;
        }

        return ['avisados' => $avisados, 'sin_correo' => $sinCorreo];
    }

    /**
     * Dirección del propietario responsable del pago. Sin exigir que esté verificada: si
     * no se le puede escribir hasta que confirme, y para confirmar hay que escribirle,
     * no se sale nunca. Que esté o no verificada se ve en la lista de propietarios.
     */
    private function correoDe(?Recibo $recibo): ?Contacto
    {
        return $recibo?->propietario?->persona?->contactos
            ->where('tipo_contacto_id', TipoContacto::EMAIL)
            ->where('estado_id', Estado::ESTADO_ACTIVO)
            ->first();
    }

    private function registrar(Recibo $recibo, string $motivo, string $destinatario): void
    {
        AvisoRecibo::create([
            'recibo_id'    => $recibo->id,
            'motivo'       => $motivo,
            'destinatario' => $destinatario,
            'enviado_at'   => now(),
            // Nulo si no hay sesión: lo mandó un proceso, no una persona.
            'user_id' => auth()->id(),
        ]);
    }
}
