<?php

namespace App\Services\Contabilidad;

/**
 * Un tercero tal y como lo nombra quien manda el asiento.
 *
 * `tipo` e `id` son la etiqueta opaca con la que ese sistema reconoce al tercero: la
 * contabilidad los guarda y los compara, pero no los interpreta. `clase` sí es
 * vocabulario contable (cliente, proveedor, acreedor, deudor) y decide de qué grupo
 * cuelga la subcuenta.
 */
final readonly class DatosTercero
{
    public function __construct(
        public string $tipo,
        public string $id,
        public ?string $clase = null,
        public ?string $nif = null,
        public ?string $razonSocial = null,
    ) {
    }

    public static function desdeArray(array $datos): self
    {
        return new self(
            tipo: (string) $datos['tipo'],
            id: (string) $datos['id'],
            clase: $datos['clase'] ?? null,
            nif: $datos['nif'] ?? null,
            razonSocial: $datos['razon_social'] ?? null,
        );
    }
}
