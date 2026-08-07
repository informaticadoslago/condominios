<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Ajustes del sistema que se tocan desde una pantalla (tabla `configuraciones`).
 *
 * Clave y valor, los dos texto: cada ajuste sabe leer lo suyo. Lo que no se cambia en
 * caliente —credenciales, interruptores de despliegue— se queda en el .env.
 */
class Configuracion extends Model
{
    // Explícito: Eloquent adivinaría «configuracions».
    protected $table = 'configuraciones';

    protected $fillable = ['clave', 'valor'];

    /** Cuánto duran los tokens de API nuevos. Vacío = no caducan. */
    public const CADUCIDAD_TOKENS = 'tokens_api.caducidad';

    /**
     * Las duraciones que se pueden elegir. La clave es lo que se guarda y lo entiende
     * Carbon tal cual; el valor, lo que se lee en pantalla.
     */
    public const DURACIONES_TOKENS = [
        ''           => 'No caducan',
        '+30 days'   => '30 días',
        '+90 days'   => '90 días',
        '+6 months'  => '6 meses',
        '+1 year'    => '1 año',
        '+2 years'   => '2 años',
    ];

    public static function valor(string $clave, ?string $porDefecto = null): ?string
    {
        $fila = static::where('clave', $clave)->first();

        return $fila ? $fila->valor : $porDefecto;
    }

    public static function poner(string $clave, ?string $valor): void
    {
        static::updateOrCreate(['clave' => $clave], ['valor' => $valor]);
    }

    /**
     * Cuándo caduca un token creado ahora mismo, o null si no debe caducar.
     *
     * Se resuelve en el momento de crearlo: cambiar el ajuste después no alarga ni
     * acorta los que ya existen, cada token se lleva su fecha puesta.
     */
    public static function caducidadTokensApi(): ?CarbonInterface
    {
        $duracion = trim((string) static::valor(static::CADUCIDAD_TOKENS, ''));

        if ($duracion === '' || ! array_key_exists($duracion, static::DURACIONES_TOKENS)) {
            return null;
        }

        return now()->modify($duracion);
    }
}
