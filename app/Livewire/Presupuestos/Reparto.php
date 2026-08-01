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

        $datosPagoCompletos = $presupuesto->fecha_primer_pago && $presupuesto->periodicidad_id;
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
            $miembros         = $datosGrupo['grupo']->inmuebles;
            $sumaCoeficientes = $miembros->sum(fn ($i) => (float) ($i->pivot->coeficiente ?? $i->coeficiente));

            $datosGrupo['sumaCoeficientes'] = $sumaCoeficientes;
            $datosGrupo['lineas']           = $miembros->map(function ($inmueble) use ($datosGrupo, $sumaCoeficientes) {
                $coeficiente = (float) ($inmueble->pivot->coeficiente ?? $inmueble->coeficiente);
                $importe     = $sumaCoeficientes > 0 ? $datosGrupo['total'] * $coeficiente / $sumaCoeficientes : 0.0;

                return ['inmueble' => $inmueble, 'coeficiente' => $coeficiente, 'importe' => $importe];
            })->sortBy(fn ($l) => [$l['inmueble']->planta, $l['inmueble']->puerta])->values();

            foreach ($datosGrupo['lineas'] as $linea) {
                $id            = $linea['inmueble']->id;
                $global[$id] ??= ['inmueble' => $linea['inmueble'], 'total' => 0.0];
                $global[$id]['total'] += $linea['importe'];
            }
        }
        unset($datosGrupo);

        $fechasPagos  = $datosPagoCompletos ? $this->fechasPagos($presupuesto) : [];
        $numeroPagos  = $datosPagoCompletos ? Presupuesto::numeroPagosPara($presupuesto->periodicidad->meses) : 0;

        foreach ($global as &$fila) {
            $fila['pagos'] = $datosPagoCompletos ? $this->desglosePagos($fila['total'], $numeroPagos) : [];
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

        return collect(range(1, Presupuesto::numeroPagosPara($meses)))
            ->map(fn ($i) => $inicio->copy()->addMonthsNoOverflow(($i - 1) * $meses))
            ->all();
    }

    /** Reparte $total en $n pagos iguales; el redondeo se ajusta en el primero. */
    protected function desglosePagos(float $total, int $n): array
    {
        if ($n <= 0) {
            return [];
        }

        $cuota = round($total / $n, 2);
        $pagos = array_fill(0, $n, $cuota);

        if ($n > 1) {
            $pagos[0] = round($total - $cuota * ($n - 1), 2);
        } else {
            $pagos[0] = round($total, 2);
        }

        return $pagos;
    }
}
