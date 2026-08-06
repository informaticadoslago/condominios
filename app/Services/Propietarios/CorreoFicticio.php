<?php

namespace App\Services\Propietarios;

use App\Models\Contacto;
use App\Models\Estado;
use App\Models\PersonaComunidad;
use App\Models\TipoContacto;
use Illuminate\Support\Str;

/**
 * Direcciones de correo de mentira para los datos de demo: nombre + primer apellido en
 * @condominios2lago.com.
 *
 * Vive aparte del seeder porque lo usan dos: el fakeseed, al crear propietarios nuevos, y
 * el comando que rellena los que ya estaban. Si cada uno lo construyera por su cuenta,
 * relanzar uno después del otro dejaría dos direcciones distintas para la misma persona.
 *
 * El dominio no existe a propósito: son datos de demo y no queremos que un envío de
 * pruebas llegue a nadie.
 */
final class CorreoFicticio
{
    public const DOMINIO = 'condominios2lago.com';

    /**
     * Le pone la dirección si no tiene ninguna. Si ya tiene, no se toca: puede ser una
     * de verdad puesta a mano para probar.
     *
     * @return bool si se ha creado
     */
    public function asignarA(PersonaComunidad $persona): bool
    {
        $yaTiene = $persona->contactos()
            ->where('tipo_contacto_id', TipoContacto::EMAIL)
            ->exists();

        if ($yaTiene) {
            return false;
        }

        $persona->contactos()->create([
            'tipo_contacto_id' => TipoContacto::EMAIL,
            'descripcion'      => 'Email',
            'valor'            => $this->direccionLibre($persona),
            'estado_id'        => Estado::ESTADO_ACTIVO,
        ]);

        return true;
    }

    /**
     * nombre + primer apellido, sin acentos ni espacios y en minúsculas:
     * «Roberto Delgado Vega» → robertodelgado@condominios2lago.com
     */
    public function direccion(PersonaComunidad $persona): string
    {
        $local = Str::of($persona->nombre.' '.$persona->apellido1)
            ->ascii()
            ->lower()
            // Fuera todo lo que no sea letra o número: espacios, guiones de apellidos
            // compuestos y apóstrofos ("O'Donnell", "Sáez-Díez").
            ->replaceMatches('/[^a-z0-9]/', '')
            ->toString();

        return ($local ?: 'propietario').'@'.self::DOMINIO;
    }

    /**
     * La misma, con un número detrás si ya la tiene otro: en una comunidad puede haber
     * dos «Carmen Ruiz», y la dirección tiene que seguir siendo única.
     */
    private function direccionLibre(PersonaComunidad $persona): string
    {
        $base = $this->direccion($persona);

        if (! $this->estaCogida($base)) {
            return $base;
        }

        [$local, $dominio] = explode('@', $base);

        for ($i = 2; $i < 1000; $i++) {
            $candidata = $local.$i.'@'.$dominio;

            if (! $this->estaCogida($candidata)) {
                return $candidata;
            }
        }

        // Con 998 homónimos algo va mal; antes que fallar, una dirección segura.
        return $local.'-'.$persona->id.'@'.$dominio;
    }

    private function estaCogida(string $direccion): bool
    {
        return Contacto::where('tipo_contacto_id', TipoContacto::EMAIL)
            ->where('valor', $direccion)
            ->exists();
    }
}
