<?php

namespace App\Services\ComisionesBancarias;

use App\Models\ApunteContable;
use App\Models\AsientoContable;
use App\Models\ComisionBancaria;
use Illuminate\Support\Facades\DB;

/**
 * Borra una comisión bancaria y su asiento, para poder repetirla bien.
 *
 * Sirve para el caso real de teclearla mal (fecha, importe, cuenta...) y darse cuenta
 * enseguida: nada más se apoya en este asiento en concreto, así que se puede quitar de
 * en medio sin dejar rastro que cuadrar. No es el camino para deshacer un cargo que de
 * verdad ocurrió: eso se corrige con un apunte contrario, no borrando.
 */
final class DeshacerComisionBancaria
{
    public function ejecutar(ComisionBancaria $comision): void
    {
        DB::transaction(function () use ($comision) {
            if ($comision->asiento_contable !== null) {
                ApunteContable::where('asiento_contable_id', $comision->asiento_contable)->delete();
                AsientoContable::where('id', $comision->asiento_contable)->delete();
            }

            $comision->lineas()->delete();
            $comision->delete();
        });
    }
}
