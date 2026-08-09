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

        foreach ($global as &$fila) {
            $fila['pagos'] = $datosPagoCompletos
                ? Presupuesto::repartirPagos($fila['total'], $presupuesto->numero_pagos)
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
