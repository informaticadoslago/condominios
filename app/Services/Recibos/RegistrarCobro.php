<?php

namespace App\Services\Recibos;

use App\Models\Cobro;
use App\Models\FormaDePago;
use App\Models\Recibo;
use App\Models\Remesa;
use App\Models\TipoEstadoRecibo;
use Illuminate\Support\Facades\DB;

/**
 * Entrada única de dinero sobre un recibo, venga del canal que venga (transferencia,
 * efectivo, remesa). Escribe siempre dos cosas a la vez: la fila en `cobros` —el hecho
 * fechado que luego referencia la contabilidad— y el `importe_pagado` del recibo, que
 * es la suma de sus cobros. El `saldo` no se toca: lo recalcula el motor.
 *
 * Nunca corrige ni borra un cobro anterior; para deshacer se registra el contrario (un
 * importe negativo), tal y como dice Cobro.
 */
class RegistrarCobro
{
    /**
     * Cobra un recibo. Sin importe explícito cobra lo que queda pendiente, que es el
     * caso normal: llega la transferencia por el recibo entero.
     *
     * Devuelve null si no había nada que cobrar (ya estaba pagado, o se llamó dos veces
     * sobre el mismo recibo), para que el llamante pueda contar cuántos cobró de verdad.
     */
    public function registrar(int $reciboId, string $fecha, int $formaDePagoId, ?float $importe = null, ?int $lineaRemesaId = null): ?Cobro
    {
        return DB::transaction(function () use ($reciboId, $fecha, $formaDePagoId, $importe, $lineaRemesaId) {
            // Bloqueada la fila: dos usuarios cobrando el mismo recibo a la vez leerían
            // el mismo pendiente y lo cobrarían dos veces.
            $recibo = Recibo::whereKey($reciboId)->lockForUpdate()->first();

            if (! $recibo) {
                return null;
            }

            // Lo que se debe, no lo que se emitió: si le devolvieron el recibo, la comisión
            // que el banco cobró se le repercutió y se cobra con él.
            $pendiente = $this->deuda($recibo) - (float) $recibo->importe_pagado;
            $importe   = $importe ?? $pendiente;

            if ($importe <= 0) {
                return null;
            }

            $cobro = Cobro::create([
                'recibo_id'        => $recibo->id,
                'forma_de_pago_id' => $formaDePagoId,
                // De qué presentación vino, cuando viene de una. Sin esto, una devolución
                // posterior no sabría qué cobro tiene que deshacer.
                'linea_remesa_id'  => $lineaRemesaId,
                'fecha'            => $fecha,
                'importe'          => $importe,
            ]);

            $recibo->importe_pagado = (float) $recibo->importe_pagado + $importe;

            // El estado es el ciclo de vida del recibo, no cuánto se debe: solo pasa a
            // Cobrado cuando no queda nada pendiente. Un cobro parcial lo deja como está.
            if ((float) $recibo->importe_pagado >= $this->deuda($recibo)) {
                if ($lineaRemesaId) {
                    $recibo->motivoCambioEstado = __('Cobrada la remesa :referencia', [
                        'referencia' => $recibo->lineasRemesas()->whereKey($lineaRemesaId)->first()?->remesa?->referencia,
                    ]);
                }

                $recibo->fechaCambioEstado = $fecha;
                $recibo->estado_id = TipoEstadoRecibo::COBRADO;
            }

            $recibo->save();

            return $cobro;
        });
    }

    /**
     * Da por cobrada una remesa: el banco carga todo lo presentado, así que se cobra lo
     * que NO ha vuelto devuelto. Se hace a mano y pasado el plazo de devolución, porque
     * hasta entonces un adeudo presentado todavía puede rebotar.
     *
     * Cada cobro queda enganchado a su línea, que es la que devuelve el banco si más
     * tarde rebota: entonces RegistrarDevolucion sabe qué importe deshacer.
     *
     * @return int recibos cobrados de verdad (los ya pagados no cuentan)
     */
    public function registrarRemesa(Remesa $remesa, string $fecha): int
    {
        return DB::transaction(function () use ($remesa, $fecha) {
            $cobrados = 0;

            $lineas = $remesa->lineas()->whereNull('fecha_devolucion')->get();

            foreach ($lineas as $linea) {
                $cobro = $this->registrar(
                    (int) $linea->recibo_id,
                    $fecha,
                    FormaDePago::RECIBO_BANCARIO,
                    null,
                    (int) $linea->id,
                );

                if ($cobro) {
                    $cobrados++;
                }
            }

            return $cobrados;
        });
    }

    /**
     * Lo que hay que cobrarle al propietario: su cuota más las comisiones de las veces
     * que le devolvieron el recibo, que se le repercuten. Es lo mismo que suma la columna
     * `saldo`, pero calculado aquí porque el recibo puede venir ya modificado en memoria.
     */
    private function deuda(Recibo $recibo): float
    {
        return (float) $recibo->importe + (float) $recibo->gastos_devolucion;
    }

    /**
     * Cobra de golpe todos los recibos indicados por su pendiente completo. Devuelve
     * cuántos se cobraron: los que ya estaban pagados no cuentan.
     */
    public function registrarVarios(array $reciboIds, string $fecha, int $formaDePagoId): int
    {
        return DB::transaction(function () use ($reciboIds, $fecha, $formaDePagoId) {
            $cobrados = 0;

            foreach ($reciboIds as $reciboId) {
                if ($this->registrar((int) $reciboId, $fecha, $formaDePagoId)) {
                    $cobrados++;
                }
            }

            return $cobrados;
        });
    }
}
