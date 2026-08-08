<?php

namespace App\Services\Facturas;

use App\Models\CuentaBancaria;
use App\Models\FacturaProveedor;
use App\Models\PagoFactura;
use Illuminate\Support\Facades\DB;

/**
 * Salida única de dinero sobre una factura de proveedor, espejo de RegistrarCobro.
 *
 * Escribe siempre dos cosas a la vez: la fila en `pagos_facturas` —el hecho fechado que
 * luego referencia la contabilidad— y el `importe_pagado` de la factura, que es la suma
 * de sus pagos.
 *
 * El dinero sale de la cuenta bancaria de la comunidad. Si tiene más de una no se elige
 * aquí a ciegas: se avisa y no se paga, porque de qué cuenta salió el dinero no es algo
 * que pueda suponer el programa.
 */
final class RegistrarPagoFactura
{
    public function __construct(private readonly EnlazarPagosContabilidad $contabilidad)
    {
    }

    /**
     * Paga una factura. Sin importe explícito paga lo que queda pendiente, que es el caso
     * normal. Devuelve null si no había nada que pagar.
     */
    public function registrar(int $facturaId, string $fecha, ?float $importe = null): ?PagoFactura
    {
        $pago = DB::transaction(function () use ($facturaId, $fecha, $importe) {
            // Bloqueada la fila: dos usuarios pagando la misma factura a la vez leerían el
            // mismo pendiente y la pagarían dos veces.
            $factura = FacturaProveedor::whereKey($facturaId)->lockForUpdate()->first();

            if (! $factura) {
                return null;
            }

            $importe = $importe ?? $factura->pendiente();

            if (round($importe, 2) <= 0) {
                return null;
            }

            $cuenta = $this->cuentaBancaria($factura);

            if (! $cuenta) {
                return null;
            }

            $pago = PagoFactura::create([
                'factura_proveedor_id' => $factura->id,
                'cuenta_bancaria_id'   => $cuenta->id,
                'fecha'                => $fecha,
                'importe'              => round($importe, 2),
            ]);

            $factura->update([
                'importe_pagado' => round((float) $factura->importe_pagado + round($importe, 2), 2),
            ]);

            return $pago;
        });

        // Fuera de la transacción del pago: que la contabilidad falle no deshace un pago
        // que en la gestión ya ocurrió, igual que en recibos.
        if ($pago) {
            $this->contabilidad->ejecutar([$pago->id]);
        }

        return $pago;
    }

    /**
     * Por qué esta factura no se puede pagar todavía. Null si se puede.
     */
    public function motivoNoPagable(FacturaProveedor $factura): ?string
    {
        if ($factura->pendiente() <= 0) {
            return __('Esta factura ya está pagada.');
        }

        $comunidad = $factura->proveedor?->persona?->comunidad;

        if (! $comunidad) {
            return __('La factura no tiene comunidad.');
        }

        $cuentas = $comunidad->cuentasBancarias()->count();

        if ($cuentas === 0) {
            return __('La comunidad no tiene ninguna cuenta bancaria.');
        }

        if ($cuentas > 1) {
            return __('La comunidad tiene varias cuentas bancarias: no se sabe de cuál sale el dinero.');
        }

        // Con contabilidad, el gasto tiene que estar asentado antes que su pago: si no, el
        // acreedor quedaría con saldo a favor y el mayor sin sentido.
        if ($comunidad->empresa_contable_id !== null && $factura->asiento_contable === null) {
            return __('Contabilice antes la factura: el pago va contra el acreedor que crea ese asiento.');
        }

        return null;
    }

    private function cuentaBancaria(FacturaProveedor $factura): ?CuentaBancaria
    {
        $cuentas = $factura->proveedor?->persona?->comunidad?->cuentasBancarias()->get();

        return $cuentas?->count() === 1 ? $cuentas->first() : null;
    }
}
