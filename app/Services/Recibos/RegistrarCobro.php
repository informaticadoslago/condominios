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
     *
     * @throws \RuntimeException si es una transferencia y los recibos son de propietarios
     *                           distintos: una transferencia es un único movimiento de
     *                           una cuenta, no puede saldar la de varios a la vez.
     */
    public function registrarVarios(array $reciboIds, string $fecha, int $formaDePagoId): int
    {
        return DB::transaction(function () use ($reciboIds, $fecha, $formaDePagoId) {
            if ($formaDePagoId === FormaDePago::TRANSFERENCIA
                && Recibo::whereIn('id', $reciboIds)->distinct()->count('propietario_id') > 1) {
                throw new \RuntimeException(__('Una transferencia es un único movimiento de un propietario: no se puede usar para cobrar recibos de varios propietarios a la vez.'));
            }

            $cobrados = 0;

            foreach ($reciboIds as $reciboId) {
                if ($this->registrar((int) $reciboId, $fecha, $formaDePagoId)) {
                    $cobrados++;
                }
            }

            return $cobrados;
        });
    }

    /**
     * Cobra varios recibos de un mismo propietario contra un único importe recibido —una
     * transferencia que cubre varias cuotas de golpe. No hay pagos parciales: si el
     * importe no llega a cubrir lo pendiente de todos los recibos, no se cobra ninguno,
     * para eso está `registrarVarios`.
     *
     * Si sobra, el sobrante no es de ningún recibo —un recibo no se paga por más de lo
     * que vale— así que sale como un cobro suelto, sin recibo, abonado directamente al
     * propietario: saldo a favor sin aplicar todavía a ningún vencimiento futuro.
     *
     * @param  int[]  $reciboIds
     * @return array{cobrados: int, sobrante: float}
     *
     * @throws \RuntimeException si los recibos son de propietarios distintos, o el
     *                           importe no llega a cubrir lo pendiente
     */
    public function registrarPago(array $reciboIds, string $fecha, int $formaDePagoId, float $importeRecibido): array
    {
        return DB::transaction(function () use ($reciboIds, $fecha, $formaDePagoId, $importeRecibido) {
            $recibos = Recibo::whereIn('id', $reciboIds)->lockForUpdate()->get();

            $propietarioId = $recibos->pluck('propietario_id')->unique();

            if ($propietarioId->count() > 1) {
                throw new \RuntimeException(__('Los recibos seleccionados son de propietarios distintos: no se puede repartir un único importe entre varios.'));
            }

            $pendienteTotal = $recibos->sum(fn (Recibo $recibo) => $this->deuda($recibo) - (float) $recibo->importe_pagado);

            if (round($importeRecibido, 2) < round($pendienteTotal, 2)) {
                throw new \RuntimeException(__('El importe recibido no llega a cubrir lo pendiente de los recibos seleccionados (:pendiente €): no se admiten cobros parciales desde aquí.', [
                    'pendiente' => number_format($pendienteTotal, 2, ',', '.'),
                ]));
            }

            $cobrados = 0;

            foreach ($recibos as $recibo) {
                if ($this->registrar($recibo->id, $fecha, $formaDePagoId)) {
                    $cobrados++;
                }
            }

            $sobrante = round($importeRecibido - $pendienteTotal, 2);

            if ($sobrante > 0) {
                Cobro::create([
                    'propietario_id'   => $propietarioId->first(),
                    'forma_de_pago_id' => $formaDePagoId,
                    'fecha'            => $fecha,
                    'importe'          => $sobrante,
                ]);
            }

            return ['cobrados' => $cobrados, 'sobrante' => $sobrante];
        });
    }
}
