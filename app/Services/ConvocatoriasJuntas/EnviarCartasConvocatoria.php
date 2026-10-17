<?php

namespace App\Services\ConvocatoriasJuntas;

use App\Mail\CartaConvocatoria;
use App\Models\ConvocatoriaJunta;
use App\Models\CorreoEnviado;
use App\Models\Inmueble;
use App\Models\Propietario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * Manda por correo la carta de convocatoria, inmueble a inmueble: a cada propietario
 * vigente del inmueble que tenga dirección, un correo con la carta de ese inmueble adjunta.
 *
 * Como los avisos de recibo (ver EnviarAvisosRecibos): se escribe a la dirección aunque no
 * esté validada (quien la tiene sin validar sale en rojo y empieza sin marcar), y el rastro
 * en correos_enviados se deja al encolar, no cuando el servidor lo entrega.
 */
final class EnviarCartasConvocatoria
{
    /**
     * Una línea por inmueble, en orden de planta, para el modal de envío.
     *
     * @return Collection<int, array{inmueble_id: int, nomenclatura: string, propietarios: array<int, array{nombre: string, correo: ?string, validado: bool}>, con_correo: bool, validado: bool}>
     */
    public function lineas(ConvocatoriaJunta $convocatoria): Collection
    {
        $inmuebles = $convocatoria->comunidad->inmuebles()
            ->with(['tipoInmueble', 'propietarios.persona.contactos'])
            ->get();

        return Inmueble::ordenarPorPlanta($inmuebles)->map(function (Inmueble $inmueble) {
            $propietarios = $inmueble->propietarios->map(fn (Propietario $propietario) => [
                'nombre'   => $propietario->persona?->nombreCompleto,
                'correo'   => $propietario->correo()?->valor,
                'validado' => (bool) $propietario->correo()?->estaValidado(),
            ])->values();

            return [
                'inmueble_id'  => $inmueble->id,
                'nomenclatura' => $inmueble->nomenclaturaOSugerida(),
                'propietarios' => $propietarios->all(),
                'con_correo'   => $propietarios->contains(fn ($p) => $p['correo'] !== null),
                'validado'     => $propietarios->contains(fn ($p) => $p['validado']),
            ];
        })->values();
    }

    /**
     * Encola un correo por propietario con dirección. Devuelve cuántos se han mandado.
     * $cc/$cco (sueltos, como en el aviso de cargo) van solo en el primero: si el inmueble
     * tiene varios titulares, quien va en copia no tiene por qué recibirlo repetido.
     */
    public function enviarInmueble(ConvocatoriaJunta $convocatoria, Inmueble $inmueble, ?string $cc = null, ?string $cco = null): int
    {
        $enviados = 0;

        foreach ($inmueble->propietarios()->with('persona.contactos')->get() as $propietario) {
            $correo = $propietario->correo();

            if (! $correo) {
                continue;
            }

            $mailable = new CartaConvocatoria($convocatoria, $inmueble, $propietario->persona?->nombreCompleto);

            $envio = Mail::to($correo->valor);

            if ($enviados === 0 && $cc) {
                $envio->cc($cc);
            }

            if ($enviados === 0 && $cco) {
                $envio->bcc($cco);
            }

            $envio->queue($mailable);

            CorreoEnviado::create([
                'tipo'         => $mailable::class,
                'asunto'       => $mailable->asunto(),
                'destinatario' => $correo->valor,
                'enviado_at'   => now(),
                // Nulo si no hay sesión: lo mandó un proceso, no una persona.
                'user_id' => auth()->id(),
            ]);

            $enviados++;
        }

        return $enviados;
    }
}
