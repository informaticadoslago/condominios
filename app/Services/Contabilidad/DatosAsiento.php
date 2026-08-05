<?php

namespace App\Services\Contabilidad;

/**
 * Un asiento completo listo para registrar. Es la frontera del módulo contable: aquí no
 * entra ningún modelo de fuera, y por eso da igual que quien llame sea la gestión de
 * comunidades, un importador o un sistema ajeno entrando por HTTP.
 */
final readonly class DatosAsiento
{
    /**
     * @param  list<DatosApunte>  $lineas
     */
    public function __construct(
        public int $empresaContableId,
        public string $ejercicio,
        public string $fecha,
        public string $concepto,
        public array $lineas,
        public ?string $diario = null,
        public ?string $referenciaTipo = null,
        public ?string $referenciaId = null,
        public ?string $evento = null,
        public bool $crearTercerosDesconocidos = false,
    ) {
    }

    public static function desdeArray(array $datos): self
    {
        return new self(
            empresaContableId: (int) $datos['empresa_contable_id'],
            ejercicio: (string) $datos['ejercicio'],
            fecha: (string) $datos['fecha'],
            concepto: (string) $datos['concepto'],
            lineas: array_map(
                static fn (array $linea): DatosApunte => DatosApunte::desdeArray($linea),
                array_values($datos['lineas']),
            ),
            diario: $datos['diario'] ?? null,
            referenciaTipo: $datos['referencia']['tipo'] ?? null,
            referenciaId: isset($datos['referencia']['id']) ? (string) $datos['referencia']['id'] : null,
            evento: $datos['referencia']['evento'] ?? null,
            crearTercerosDesconocidos: (bool) ($datos['crear_terceros_desconocidos'] ?? false),
        );
    }

    /** Solo con la terna completa hay clave de idempotencia; los asientos manuales no la tienen. */
    public function tieneReferencia(): bool
    {
        return $this->referenciaTipo !== null && $this->referenciaId !== null && $this->evento !== null;
    }

    public function totalDebe(): int
    {
        return array_sum(array_map(static fn (DatosApunte $l): int => $l->debe, $this->lineas));
    }

    public function totalHaber(): int
    {
        return array_sum(array_map(static fn (DatosApunte $l): int => $l->haber, $this->lineas));
    }
}
