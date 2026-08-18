<?php

namespace App\Services\ComisionesBancarias;

use App\Models\ComisionBancaria;
use App\Models\TipoComisionBancaria;
use Illuminate\Support\Facades\DB;

/**
 * Da de alta a mano un cargo bancario fuera del circuito de recibos —comisión de
 * liquidar una remesa, o mantenimiento y administración de cuenta— y lo enlaza a
 * contabilidad en el mismo acto: rellenar este formulario ya es la decisión explícita
 * de contabilizarlo, como pagar una factura.
 */
final class RegistrarComisionBancariaService
{
    public function __construct(private readonly EnlazarComisionesBancariasContabilidad $contabilidad)
    {
    }

    /**
     * @param  array<int, array{concepto: string, importe: float|string}>  $lineas
     */
    public function registrar(
        int $cuentaBancariaId,
        int $tipoComisionBancariaId,
        ?int $remesaId,
        string $fecha,
        string $concepto,
        ?string $referencia,
        array $lineas,
    ): ComisionBancaria {
        $tipo = TipoComisionBancaria::findOrFail($tipoComisionBancariaId);

        $comision = DB::transaction(function () use ($cuentaBancariaId, $tipo, $remesaId, $fecha, $concepto, $referencia, $lineas) {
            $comision = ComisionBancaria::create([
                'cuenta_bancaria_id'        => $cuentaBancariaId,
                'tipo_comision_bancaria_id' => $tipo->id,
                // El mantenimiento no viene de ninguna remesa: aunque llegara marcada,
                // no pertenece a este tipo.
                'remesa_id' => in_array($tipo->codigo, [TipoComisionBancaria::REMESA, TipoComisionBancaria::DEVOLUCION], true)
                    ? $remesaId
                    : null,
                'fecha'                     => $fecha,
                'concepto'                  => $concepto,
                'referencia'                => $referencia,
            ]);

            foreach ($lineas as $linea) {
                $comision->lineas()->create([
                    'concepto' => $linea['concepto'],
                    'importe'  => round((float) $linea['importe'], 2),
                ]);
            }

            return $comision;
        });

        // Fuera de la transacción del alta: que la contabilidad falle no deshace un
        // hecho que en la gestión ya ocurrió, igual que en el pago de facturas.
        $this->contabilidad->ejecutar([$comision->id]);

        return $comision->fresh('lineas');
    }
}
