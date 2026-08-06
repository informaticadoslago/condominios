<?php

namespace App\Services\Recibos;

use App\Models\Cobro;
use App\Models\Recibo;
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
    public function registrar(int $reciboId, string $fecha, int $formaDePagoId, ?float $importe = null): ?Cobro
    {
        return DB::transaction(function () use ($reciboId, $fecha, $formaDePagoId, $importe) {
            // Bloqueada la fila: dos usuarios cobrando el mismo recibo a la vez leerían
            // el mismo pendiente y lo cobrarían dos veces.
            $recibo = Recibo::whereKey($reciboId)->lockForUpdate()->first();

            if (! $recibo) {
                return null;
            }

            $pendiente = (float) $recibo->importe - (float) $recibo->importe_pagado;
            $importe   = $importe ?? $pendiente;

            if ($importe <= 0) {
                return null;
            }

            $cobro = Cobro::create([
                'recibo_id'        => $recibo->id,
                'forma_de_pago_id' => $formaDePagoId,
                'fecha'            => $fecha,
                'importe'          => $importe,
            ]);

            $recibo->importe_pagado = (float) $recibo->importe_pagado + $importe;

            // El estado es el ciclo de vida del recibo, no cuánto se debe: solo pasa a
            // Cobrado cuando no queda nada pendiente. Un cobro parcial lo deja como está.
            if ((float) $recibo->importe_pagado >= (float) $recibo->importe) {
                $recibo->estado_id = TipoEstadoRecibo::COBRADO;
            }

            $recibo->save();

            return $cobro;
        });
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
