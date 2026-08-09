<?php

namespace App\Services\Presupuestos;

use App\Models\Presupuesto;
use App\Models\TipoEstadoPresupuesto;
use Carbon\Carbon;

/**
 * Reparto de un presupuesto entre sus inmuebles, y desglose de cada uno en sus pagos.
 *
 * Vive fuera del componente Livewire porque lo consultan dos sitios que NO pueden dar
 * resultados distintos: la pantalla de reparto y el generador de recibos que vuelca ese
 * mismo reparto a disco al aprobar. Si cada uno lo calculara por su cuenta, el día que
 * uno de los dos cambiara los recibos dejarían de coincidir con lo aprobado en junta.
 */
final class CalculadorReparto
{
    /**
     * @return array{
     *     datosPagoCompletos: bool,
     *     total: float,
     *     grupos: Collection,
     *     global: Collection,
     *     fechasPagos: Carbon[]
     * }
     */
    public function calcular(Presupuesto $presupuesto): array
    {
        $reparto = $this->calcularEnVivo($presupuesto);

        if ($presupuesto->estado_id == TipoEstadoPresupuesto::APROBADO) {
            $reparto = $this->aplicarRecibosExistentes($presupuesto, $reparto);
        }

        return $reparto;
    }

    /**
     * Reparto recalculado desde cero a partir de conceptos, grupos de reparto y el
     * porcentaje de cada pago — sin mirar los recibos ya generados. Es el que se vuelca a
     * `recibos` al aprobar (ver aplicarRecibosExistentes(): una vez hay recibos, son la
     * fuente de verdad y esto deja de usarse para ese presupuesto).
     */
    private function calcularEnVivo(Presupuesto $presupuesto): array
    {
        $presupuesto->loadMissing(['periodicidad', 'conceptos.grupoDeReparto.inmuebles']);

        $datosPagoCompletos = (bool) ($presupuesto->fecha_primer_pago && $presupuesto->periodicidad_id && $presupuesto->numero_pagos);
        $total              = (float) $presupuesto->conceptos->sum('importe');

        // Por grupo de reparto: total de sus conceptos, repartido entre sus miembros
        // proporcionalmente al coeficiente (el fijado en el grupo, si lo hay; si no, el
        // propio del inmueble).
        $grupos = [];
        foreach ($presupuesto->conceptos as $concepto) {
            $grupo = $concepto->grupoDeReparto;
            if (! $grupo) {
                continue;
            }

            $grupos[$grupo->id] ??= ['grupo' => $grupo, 'total' => 0.0];
            $grupos[$grupo->id]['total'] += (float) $concepto->importe;
        }

        $global = []; // inmueble_id => ['inmueble' => Inmueble, 'total' => float]

        foreach ($grupos as &$datosGrupo) {
            // El orden importa: Presupuesto::repartirProporcional() da el céntimo de más
            // a los primeros de la lista, así que se ordena ANTES de repartir (no después).
            $miembros = $datosGrupo['grupo']->inmuebles
                ->sortBy(fn ($i) => [$i->planta, $i->puerta])
                ->values();

            $pesos    = $miembros->mapWithKeys(fn ($i) => [$i->id => (float) ($i->pivot->coeficiente ?? $i->coeficiente)])->all();
            $importes = Presupuesto::repartirProporcional($datosGrupo['total'], $pesos, $datosGrupo['grupo']->siguiente_inicio_reparto);

            $datosGrupo['sumaCoeficientes'] = array_sum($pesos);
            $datosGrupo['lineas']           = $miembros->map(fn ($inmueble) => [
                'inmueble'    => $inmueble,
                'coeficiente' => $pesos[$inmueble->id],
                'importe'     => $importes[$inmueble->id],
            ])->values();

            foreach ($datosGrupo['lineas'] as $linea) {
                $id            = $linea['inmueble']->id;
                $global[$id] ??= ['inmueble' => $linea['inmueble'], 'total' => 0.0];
                $global[$id]['total'] += $linea['importe'];
            }
        }
        unset($datosGrupo);

        $fechasPagos = $datosPagoCompletos ? $this->fechasPagos($presupuesto) : [];
        $pesosPago   = $datosPagoCompletos ? $this->pesosPago($presupuesto) : [];

        foreach ($global as &$fila) {
            $fila['pagos'] = $datosPagoCompletos
                ? array_values(Presupuesto::repartirProporcional($fila['total'], $pesosPago))
                : [];
        }
        unset($fila);

        return [
            'datosPagoCompletos' => $datosPagoCompletos,
            'total'              => $total,
            'grupos'             => collect($grupos)->values(),
            'global'             => collect($global)->sortBy(fn ($f) => [$f['inmueble']->planta, $f['inmueble']->puerta])->values(),
            'fechasPagos'        => $fechasPagos,
        ];
    }

    /**
     * Superpone al reparto en vivo los importes ya congelados en `recibos` (fuente de
     * verdad de una aprobación ya hecha: no se vuelven a tocar). Si el presupuesto
     * aprobado todavía no tiene recibos —está a punto de generarlos—, se deja el reparto
     * en vivo tal cual, que es lo que van a volcar.
     */
    private function aplicarRecibosExistentes(Presupuesto $presupuesto, array $reparto): array
    {
        $recibosPorInmueble = $presupuesto->recibos()
            ->orderBy('numero_pago')
            ->get(['inmueble_id', 'numero_pago', 'importe'])
            ->groupBy('inmueble_id');

        if ($recibosPorInmueble->isEmpty()) {
            return $reparto;
        }

        $reparto['global'] = $reparto['global']->map(function ($fila) use ($recibosPorInmueble) {
            $recibosInmueble = $recibosPorInmueble->get($fila['inmueble']->id);

            if ($recibosInmueble && $recibosInmueble->isNotEmpty()) {
                $fila['pagos'] = $recibosInmueble->pluck('importe')->map(fn ($importe) => (float) $importe)->values()->all();
            }

            return $fila;
        });

        return $reparto;
    }

    /**
     * Peso relativo de cada pago dentro del total del presupuesto: el porcentaje que el
     * usuario le dio a cada uno en la pantalla del presupuesto (un pago del 40% se lleva
     * el 40% de cada inmueble). Si no hay nada editado, o el número no cuadra con
     * numero_pagos, se reparte a partes iguales.
     *
     * @return float[]
     */
    private function pesosPago(Presupuesto $presupuesto): array
    {
        $persistidos = $presupuesto->porcentajes_pago;
        if (is_array($persistidos) && count($persistidos) === $presupuesto->numero_pagos) {
            return array_map(fn ($pct) => (float) $pct, array_values($persistidos));
        }

        return array_fill(0, $presupuesto->numero_pagos, 1.0);
    }

    /** @return Carbon[] */
    public function fechasPagos(Presupuesto $presupuesto): array
    {
        // 1) La fuente de verdad de una aprobación ya realizada son los recibos.
        if ($presupuesto->estado_id == TipoEstadoPresupuesto::APROBADO) {
            $fechas = $presupuesto->recibos()
                ->orderBy('numero_pago')
                ->get(['fecha_vencimiento'])
                ->map(fn ($recibo) => Carbon::parse($recibo->fecha_vencimiento))
                ->all();

            if ($fechas !== []) {
                return $fechas;
            }
        }

        // 2) Si el usuario ya guardó el calendario en el presupuesto, ese payload
        //    debe competir con cualquier reconstrucción motivada por la periodicidad.
        $persistidas = $presupuesto->fechas_pago;
        if (is_array($persistidas) && $persistidas !== []) {
            return collect($persistidas)
                ->map(fn ($fecha) => Carbon::parse($fecha))
                ->values()
                ->all();
        }

        $meses  = $presupuesto->periodicidad?->meses;
        $inicio = $presupuesto->fecha_primer_pago ? Carbon::parse($presupuesto->fecha_primer_pago) : null;

        if (! $meses || ! $inicio || ! $presupuesto->numero_pagos) {
            return [];
        }

        return collect(range(1, $presupuesto->numero_pagos))
            ->map(fn ($i) => $inicio->copy()->addMonthsNoOverflow(($i - 1) * $meses))
            ->all();
    }
}
