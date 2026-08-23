<?php

namespace App\Services\Recibos;

use App\Mail\AvisoDevolucion;
use App\Mail\AvisoRemesa;
use App\Mail\AvisoTransferencia;
use App\Models\Comunidad;
use App\Models\Contacto;
use App\Models\CorreoEnviado;
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
            $this->registrar($linea->recibo, $mailable, $correo->valor);

            $avisados++;
        }

        return ['avisados' => $avisados, 'sin_correo' => $sinCorreo];
    }

    /**
     * Manda el aviso de transferencia de un recibo suelto. $cc/$cco igual que en
     * enviarGrupoDevolucion: sueltos, solo se comprueba que tengan forma de correo.
     */
    public function enviarTransferencia(
        Recibo $recibo,
        Comunidad $comunidad,
        ?string $idioma = null,
        ?string $cc = null,
        ?string $cco = null,
    ): bool {
        $correo = $this->correoDe($recibo);

        if (! $correo) {
            return false;
        }

        $mailable = new AvisoTransferencia($recibo, $comunidad, $idioma);

        $envio = Mail::to($correo->valor);

        if ($cc) {
            $envio->cc($cc);
        }

        if ($cco) {
            $envio->bcc($cco);
        }

        $envio->queue($mailable);
        $this->registrar($recibo, $mailable, $correo->valor);

        return true;
    }

    /**
     * Vista previa de a quién se avisaría de las devoluciones de una remesa y cuánto,
     * agrupado por destinatario, sin mandar nada todavía: para poder enseñarlo en
     * pantalla y dejar desmarcar a alguien antes de confirmar.
     *
     * Un grupo por destinatario, no por recibo: si al mismo propietario le devuelven dos
     * inmuebles en la misma tanda (comparten mandato), es un único grupo con los dos.
     *
     * @return array{grupos: Collection<int, array{correo: string, nombre: ?string, importe: float, fecha: ?string, validado: bool, recibo_ids: array<int>}>, sin_correo: int}
     */
    public function gruposDevolucion(Remesa $remesa): array
    {
        $lineas = $remesa->lineas()
            ->whereNotNull('fecha_devolucion')
            ->with(['recibo.propietario.persona.contactos', 'recibo.inmueble', 'recibo.presupuesto'])
            ->get();

        $sinCorreo = 0;
        $grupos    = collect();

        foreach ($lineas->groupBy(fn (LineaRemesa $linea) => $this->correoDe($linea->recibo)?->valor) as $correoValor => $lineasDelGrupo) {
            if (! $correoValor) {
                $sinCorreo += $lineasDelGrupo->count();

                continue;
            }

            $recibos = $lineasDelGrupo->pluck('recibo');

            $grupos->push([
                'correo'     => $correoValor,
                'nombre'     => $recibos->first()?->propietario?->persona?->nombreCompleto,
                'importe'    => $recibos->sum(fn (Recibo $r) => (float) $r->importe + (float) $r->gastos_devolucion),
                'fecha'      => $lineasDelGrupo->first()->fecha_devolucion?->toDateString(),
                'validado'   => $this->correoDe($recibos->first())?->estaValidado() ?? false,
                'recibo_ids' => $recibos->pluck('id')->all(),
            ]);
        }

        return ['grupos' => $grupos->values(), 'sin_correo' => $sinCorreo];
    }

    /**
     * Manda el aviso de devolución a un destinatario ya agrupado (ver gruposDevolucion).
     *
     * $cc y $cco son sueltos, tecleados en el momento de mandar: no hace falta que
     * casen con ningún contacto guardado ni que estén validados, solo que sean un
     * correo con forma válida (eso se comprueba antes de llamar aquí).
     */
    public function enviarGrupoDevolucion(
        array $reciboIds,
        ?string $fecha,
        string $correoValor,
        Comunidad $comunidad,
        ?string $idioma = null,
        ?string $cc = null,
        ?string $cco = null,
    ): void {
        $recibos = Recibo::whereIn('id', $reciboIds)
            ->with(['propietario.persona', 'inmueble', 'presupuesto'])
            ->get();

        if ($recibos->isEmpty()) {
            return;
        }

        $mailable = new AvisoDevolucion($recibos, $fecha ? \Carbon\Carbon::parse($fecha) : null, $comunidad, $idioma);

        $envio = Mail::to($correoValor);

        if ($cc) {
            $envio->cc($cc);
        }

        if ($cco) {
            $envio->bcc($cco);
        }

        $envio->queue($mailable);

        foreach ($recibos as $recibo) {
            $this->registrar($recibo, $mailable, $correoValor);
        }
    }

    /**
     * Avisa de todas las devoluciones de una remesa de golpe, sin selección previa (ver
     * gruposDevolucion + enviarGrupoDevolucion para el camino con confirmación).
     *
     * @return array{avisados: int, sin_correo: int}
     */
    public function deDevolucion(Remesa $remesa, ?string $idioma = null): array
    {
        ['grupos' => $grupos, 'sin_correo' => $sinCorreo] = $this->gruposDevolucion($remesa);

        $avisados = 0;

        foreach ($grupos as $grupo) {
            $this->enviarGrupoDevolucion($grupo['recibo_ids'], $grupo['fecha'], $grupo['correo'], $remesa->comunidad, $idioma);
            $avisados += count($grupo['recibo_ids']);
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

    private function registrar(Recibo $recibo, object $mailable, string $destinatario): void
    {
        CorreoEnviado::create([
            'tipo'         => $mailable::class,
            'recibo_id'    => $recibo->id,
            'asunto'       => $mailable->asunto(),
            'destinatario' => $destinatario,
            'enviado_at'   => now(),
            // Nulo si no hay sesión: lo mandó un proceso, no una persona.
            'user_id' => auth()->id(),
        ]);
    }
}
