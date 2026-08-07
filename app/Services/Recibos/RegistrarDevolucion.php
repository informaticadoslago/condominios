<?php

namespace App\Services\Recibos;

use App\Models\Cobro;
use App\Models\LineaRemesa;
use App\Models\TipoEstadoRecibo;
use Illuminate\Support\Facades\DB;

/**
 * El banco devuelve un adeudo: se marca la línea de esa remesa y el recibo vuelve a
 * quedar a deber.
 *
 * Las devoluciones no llegan todas juntas ni el mismo día, así que esto se usa varias
 * veces sobre la misma remesa. Marcar dos veces la misma línea no hace nada: la primera
 * ya le puso fecha.
 *
 * Un recibo devuelto vuelve a entrar solo en la siguiente remesa: GeneradorRemesa
 * descarta los que tienen una línea *sin* fecha de devolución (los que siguen en vuelo),
 * y este ya no lo está.
 */
class RegistrarDevolucion
{
    /**
     * $gastos es la comisión que el banco cobró por esta devolución, que se le repercute
     * al propietario: no la asume la comunidad, que solo la adelanta. Se suma a lo que
     * debe, así que al volver a presentarle el recibo se le cobrará con ella dentro.
     */
    public function registrar(int $lineaRemesaId, string $fecha, ?string $motivo = null, float $gastos = 0): bool
    {
        return DB::transaction(function () use ($lineaRemesaId, $fecha, $motivo, $gastos) {
            $linea = LineaRemesa::whereKey($lineaRemesaId)->lockForUpdate()->first();

            if (! $linea || $linea->fecha_devolucion) {
                return false;
            }

            $recibo = $linea->recibo;

            // El banco solo puede devolver lo que llegó a cargar: mientras la remesa no se
            // haya dado por cobrada no hay devolución posible. Se comprueba ANTES de tocar
            // nada, para no dejar la línea marcada en un caso que se rechaza. La regla vive
            // aquí y no solo en la pantalla, para que valga venga la llamada de donde venga.
            $cobrado = (float) $linea->cobros()->sum('importe');

            if (! $recibo || $cobrado <= 0) {
                return false;
            }

            $linea->update([
                'fecha_devolucion'  => $fecha,
                'motivo_devolucion' => $motivo,
                'gastos_devolucion' => $gastos,
            ]);

            // Se deshace con el movimiento contrario, no borrando el cobro: la fila
            // negativa es el hecho fechado que la contabilidad referencia para su asiento
            // de devolución.
            Cobro::create([
                'recibo_id'        => $recibo->id,
                'forma_de_pago_id' => $recibo->forma_de_pago_id,
                'linea_remesa_id'  => $linea->id,
                'fecha'            => $fecha,
                'importe'          => -$cobrado,
            ]);

            $recibo->importe_pagado = (float) $recibo->importe_pagado - $cobrado;

            // La comisión se acumula: un recibo puede irse devuelto más de una vez, y cada
            // vez el banco cobra la suya.
            $recibo->gastos_devolucion = (float) $recibo->gastos_devolucion + $gastos;

            $recibo->motivoCambioEstado = $motivo
                ? __('Devuelto (:motivo)', ['motivo' => $motivo])
                : __('Devuelto por el banco');

            $recibo->estado_id = TipoEstadoRecibo::DEVUELTO;
            $recibo->save();

            return true;
        });
    }

    /**
     * Varias de golpe: las devoluciones del banco llegan en tanda.
     *
     * La comisión es la de cada devolución, no la de la tanda: si el banco carga las
     * comisiones juntas en un solo apunte, se teclea el importe unitario y la suma de
     * todas cuadra con ese cargo.
     *
     * @param  int[]  $lineaRemesaIds
     * @return int cuántas se marcaron de verdad
     */
    public function registrarVarias(array $lineaRemesaIds, string $fecha, ?string $motivo = null, float $gastos = 0): int
    {
        return DB::transaction(function () use ($lineaRemesaIds, $fecha, $motivo, $gastos) {
            $marcadas = 0;

            foreach ($lineaRemesaIds as $id) {
                if ($this->registrar((int) $id, $fecha, $motivo, $gastos)) {
                    $marcadas++;
                }
            }

            return $marcadas;
        });
    }
}
