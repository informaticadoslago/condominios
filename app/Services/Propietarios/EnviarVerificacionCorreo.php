<?php

namespace App\Services\Propietarios;

use App\Mail\VerificacionCorreoPropietario;
use App\Models\Comunidad;
use App\Models\Contacto;
use App\Models\Estado;
use App\Models\PersonaComunidad;
use App\Models\Propietario;
use App\Models\TipoContacto;
use Illuminate\Support\Facades\Mail;

/**
 * Manda a los propietarios el correo que les pide confirmar su dirección.
 *
 * Solo se escribe a direcciones sin confirmar: reenviárselo a quien ya lo confirmó es
 * ruido, y en un envío masivo de una comunidad entera se nota.
 */
final class EnviarVerificacionCorreo
{
    /** @return int correos encolados */
    public function aPropietario(Propietario $propietario, Comunidad $comunidad, ?string $idioma = null): int
    {
        return $this->enviar($this->correosSinConfirmar($propietario), $comunidad, $idioma);
    }

    /**
     * A todos los propietarios con inmueble en la comunidad. Devuelve cuántos correos se
     * han encolado, que no es lo mismo que cuántos propietarios hay: los que no tienen
     * dirección, o ya la confirmaron, no reciben nada.
     */
    public function aComunidad(Comunidad $comunidad, ?string $idioma = null): int
    {
        $propietarios = Propietario::query()
            ->whereHas('inmuebles', fn ($q) => $q->where('inmuebles.comunidad_id', $comunidad->id))
            ->with('persona.contactos')
            ->get();

        $enviados = 0;

        foreach ($propietarios as $propietario) {
            $enviados += $this->enviar($this->correosSinConfirmar($propietario), $comunidad, $idioma);
        }

        return $enviados;
    }

    /**
     * Direcciones de correo activas y sin confirmar de un propietario. Si tiene varias,
     * se escribe a todas: no hay forma de saber cuál es la buena hasta que conteste una.
     *
     * @return \Illuminate\Support\Collection<int, Contacto>
     */
    private function correosSinConfirmar(Propietario $propietario)
    {
        $persona = $propietario->persona;

        if (! $persona instanceof PersonaComunidad) {
            return collect();
        }

        return $persona->contactos()
            ->where('tipo_contacto_id', TipoContacto::EMAIL)
            ->where('estado_id', Estado::ESTADO_ACTIVO)
            ->whereNull('verified_at')
            ->get();
    }

    private function enviar($contactos, Comunidad $comunidad, ?string $idioma): int
    {
        foreach ($contactos as $contacto) {
            Mail::to($contacto->valor)->queue(
                new VerificacionCorreoPropietario($contacto, $comunidad, $idioma)
            );
        }

        return $contactos->count();
    }
}
