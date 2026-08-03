<?php

namespace App\Livewire\Presupuestos;

use App\Models\Presupuesto;
use Carbon\Carbon;
use Livewire\Component;

class Reparto extends Component
{
    public int $presupuesto_id;

    public function mount(Presupuesto $presupuesto): void
    {
        abort_if($presupuesto->comunidad_id != session('comunidad_actual_id'), 403);

        $this->presupuesto_id = $presupuesto->id;
    }

    public function render()
    {
        $presupuesto = Presupuesto::with(['periodicidad', 'conceptos.grupoDeReparto.inmuebles'])
            ->findOrFail($this->presupuesto_id);

        $datosPagoCompletos = $presupuesto->fecha_primer_pago && $presupuesto->periodicidad_id && $presupuesto->numero_pagos;
        $totalPresupuesto   = (float) $presupuesto->conceptos->sum('importe');

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

        foreach ($grupos as $grupoId => &$datosGrupo) {
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
            $fila['pagos'] = $datosPagoCompletos ? $this->desglosePagos($fila['total'], $presupuesto->numero_pagos) : [];
        }
        unset($fila);

        return view('livewire.presupuestos.reparto', [
            'presupuesto'        => $presupuesto,
            'datosPagoCompletos' => $datosPagoCompletos,
            'totalPresupuesto'   => $totalPresupuesto,
            'grupos'             => collect($grupos)->values(),
            'global'             => collect($global)->sortBy(fn ($f) => [$f['inmueble']->planta, $f['inmueble']->puerta])->values(),
            'fechasPagos'        => $fechasPagos,
        ]);
    }

    /** @return \Carbon\Carbon[] */
    protected function fechasPagos(Presupuesto $presupuesto): array
    {
        $meses  = $presupuesto->periodicidad->meses;
        $inicio = Carbon::parse($presupuesto->fecha_primer_pago);

        return collect(range(1, $presupuesto->numero_pagos))
            ->map(fn ($i) => $inicio->copy()->addMonthsNoOverflow(($i - 1) * $meses))
            ->all();
    }

    /** Reparte $total en $n pagos: ver Presupuesto::repartirPagos(). */
    protected function desglosePagos(float $total, int $n): array
    {
        return Presupuesto::repartirPagos($total, $n);
    }
}
