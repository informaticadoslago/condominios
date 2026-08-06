<?php

namespace App\Services\Recibos;

use App\Exceptions\RemesaNoAnulableException;
use App\Models\Cobro;
use App\Models\HistorialEstado;
use App\Models\Recibo;
use App\Models\Remesa;
use App\Models\TipoEstadoRecibo;
use Illuminate\Support\Facades\DB;

/**
 * Borra una remesa que no llegó a presentarse y devuelve sus recibos a como estaban.
 *
 * Sirve para el caso real de generarla mal: fecha de cargo equivocada, alguien de más o
 * de menos. Mientras no se haya cobrado, la remesa no ha tocado la contabilidad ni el
 * saldo de nadie, así que se puede quitar de en medio sin dejar rastro que cuadrar.
 *
 * En cuanto hay un cobro, no: ahí ya hay dinero contado y un asiento detrás, y eso se
 * corrige con una devolución, no borrando.
 */
class DeshacerRemesa
{
    public function ejecutar(Remesa $remesa): int
    {
        return DB::transaction(function () use ($remesa) {
            $lineas = $remesa->lineas()->with('recibo')->get();

            $cobros = Cobro::whereIn('linea_remesa_id', $lineas->pluck('id'))->count();

            if ($cobros > 0) {
                throw new RemesaNoAnulableException(
                    'Esta remesa ya tiene cobros registrados. Para deshacerla habría que anular ese dinero: '
                    .'lo que corresponde es marcar las devoluciones, no borrar la remesa.'
                );
            }

            $devueltos = 0;

            foreach ($lineas as $linea) {
                if ($linea->recibo) {
                    $this->devolverEstado($linea->recibo, $remesa);
                    $devueltos++;
                }
            }

            $remesa->lineas()->delete();
            $remesa->delete();

            return $devueltos;
        });
    }

    /**
     * Devuelve el recibo al estado que tenía justo antes de entrar en esta remesa. No se
     * da por hecho que fuera Generado: un recibo devuelto que se vuelve a presentar tiene
     * que volver a Devuelto, no aparecer como recién emitido.
     */
    private function devolverEstado(Recibo $recibo, Remesa $remesa): void
    {
        $anterior = HistorialEstado::where('estadoable_type', Recibo::class)
            ->where('estadoable_id', $recibo->id)
            ->where('estado_nuevo', TipoEstadoRecibo::ENVIADO)
            ->where('motivo', __('Remesa :referencia', ['referencia' => $remesa->referencia]))
            ->latest('id')
            ->value('estado_anterior');

        $recibo->motivoCambioEstado = __('Deshecha la remesa :referencia', ['referencia' => $remesa->referencia]);
        $recibo->estado_id          = $anterior ?? TipoEstadoRecibo::GENERADO;
        $recibo->save();
    }
}
