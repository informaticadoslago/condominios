<?php

namespace App\Services\Recibos;

use App\Exceptions\DevolucionNoAnulableException;
use App\Models\ApunteContable;
use App\Models\AsientoContable;
use App\Models\Cobro;
use App\Models\HistorialEstado;
use App\Models\LineaRemesa;
use App\Models\Recibo;
use App\Models\Remesa;
use App\Models\TipoEstadoRecibo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Deshace TODAS las devoluciones marcadas en una remesa de golpe, para poder corregir
 * una tanda mal tecleada (fecha, motivo o comisión) y repetirla bien.
 *
 * Actúa sobre la remesa entera, no línea a línea: la comisión de una tanda a veces hay
 * que repartirla entre varios recibos, y deshacer solo una dejaría el resto a medias.
 *
 * Si alguna ya entró en un ejercicio contable cerrado no se deshace nada: esa
 * contabilidad ya está presentada y no se puede alterar.
 */
final class DeshacerDevolucionesRemesa
{
    public function ejecutar(Remesa $remesa): int
    {
        return DB::transaction(function () use ($remesa) {
            $lineas = $remesa->lineas()->whereNotNull('fecha_devolucion')->with('recibo')->get();

            if ($lineas->isEmpty()) {
                throw new DevolucionNoAnulableException(
                    __('Esta remesa no tiene ninguna devolución marcada.')
                );
            }

            $cobros = Cobro::whereIn('linea_remesa_id', $lineas->pluck('id'))
                ->where('importe', '<', 0)
                ->get()
                ->keyBy('linea_remesa_id');

            $this->comprobarEjerciciosAbiertos($cobros);

            foreach ($lineas as $linea) {
                $this->deshacerLinea($linea, $cobros->get($linea->id));
            }

            return $lineas->count();
        });
    }

    /** @param  Collection<int, Cobro>  $cobros */
    private function comprobarEjerciciosAbiertos(Collection $cobros): void
    {
        $asientoIds = $cobros->pluck('asiento_contable')->filter()->unique();

        if ($asientoIds->isEmpty()) {
            return;
        }

        $cerrado = AsientoContable::whereIn('id', $asientoIds)
            ->whereHas('ejercicioContable', fn ($q) => $q->where('cerrado', true))
            ->exists();

        if ($cerrado) {
            throw new DevolucionNoAnulableException(
                __('Alguna devolución de esta tanda ya está contabilizada en un ejercicio cerrado: no se puede deshacer.')
            );
        }
    }

    /**
     * Se corrige un error, no ocurre un hecho de negocio nuevo: no debe quedar rastro de
     * que la línea pasó por Devuelto, ni contar como una transición más en el historial.
     * Por eso se borra el asiento de historial de la devolución en vez de añadir uno de
     * vuelta, y el estado se repone con withoutEvents para no disparar uno nuevo.
     */
    private function deshacerLinea(LineaRemesa $linea, ?Cobro $cobro): void
    {
        // No debería pasar: registrar() siempre crea el cobro negativo a la vez que la
        // fecha de devolución. Si faltara, no hay dinero que revertir en esta línea.
        if (! $cobro) {
            return;
        }

        if ($cobro->asiento_contable !== null) {
            ApunteContable::where('asiento_contable_id', $cobro->asiento_contable)->delete();
            AsientoContable::where('id', $cobro->asiento_contable)->delete();
        }

        $recibo = $linea->recibo;

        // El estado de antes de Devuelto: normalmente Cobrado, pero no se da por hecho,
        // igual que hace DeshacerRemesa con el suyo.
        $historial = HistorialEstado::where('estadoable_type', Recibo::class)
            ->where('estadoable_id', $recibo->id)
            ->where('estado_nuevo', TipoEstadoRecibo::DEVUELTO)
            ->latest('id')
            ->first();

        $estadoAnterior = $historial?->estado_anterior ?? TipoEstadoRecibo::COBRADO;
        $importeCobro   = abs((float) $cobro->importe);
        $gastosLinea    = (float) $linea->gastos_devolucion;

        $cobro->delete();
        $historial?->delete();

        $linea->update([
            'fecha_devolucion'  => null,
            'motivo_devolucion' => null,
            'gastos_devolucion' => 0,
        ]);

        Recibo::withoutEvents(function () use ($recibo, $estadoAnterior, $importeCobro, $gastosLinea) {
            $recibo->importe_pagado    = (float) $recibo->importe_pagado + $importeCobro;
            $recibo->gastos_devolucion = max(0, (float) $recibo->gastos_devolucion - $gastosLinea);
            $recibo->estado_id         = $estadoAnterior;
            $recibo->save();
        });
    }
}
