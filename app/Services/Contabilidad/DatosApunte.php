<?php

namespace App\Services\Contabilidad;

/**
 * Una línea de asiento tal y como entra por el servicio o por la API.
 *
 * La cuenta se indica de una de dos maneras, nunca las dos: por su código dentro de la
 * empresa contable, o por el tercero al que pertenece, que el resolvedor traduce a
 * subcuenta. Nunca por id interno: quien llama no tiene por qué conocer claves primarias
 * de la contabilidad.
 *
 * Los importes son céntimos enteros.
 */
final readonly class DatosApunte
{
    public function __construct(
        public int $debe = 0,
        public int $haber = 0,
        public ?string $cuenta = null,
        public ?DatosTercero $tercero = null,
        public ?string $concepto = null,
        public ?int $proyecto = null,
    ) {
    }

    public static function desdeArray(array $datos): self
    {
        return new self(
            debe: (int) ($datos['debe'] ?? 0),
            haber: (int) ($datos['haber'] ?? 0),
            cuenta: isset($datos['cuenta']) ? (string) $datos['cuenta'] : null,
            tercero: isset($datos['tercero']) ? DatosTercero::desdeArray($datos['tercero']) : null,
            concepto: $datos['concepto'] ?? null,
            proyecto: isset($datos['proyecto']) ? (int) $datos['proyecto'] : null,
        );
    }
}
