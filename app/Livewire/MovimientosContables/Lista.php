<?php

namespace App\Livewire\MovimientosContables;

use App\Livewire\ListaComponent;
use App\Livewire\Traits\ConEmpresaContableActiva;
use App\Livewire\Traits\ConRangoContable;
use App\Models\TipoCuentaContable;
use App\Services\Contabilidad\SaldosContablesService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Informe de movimientos entre dos fechas: los ingresos y los gastos mes a mes, el
 * resumen de dónde se sale y dónde se llega, y la justificación del saldo final.
 *
 * Es el informe que se le da a la comunidad. El rango es libre —de mayo a julio, o a
 * caballo entre dos años—, no el ejercicio: el saldo con el que arranca sale de sumar
 * todo lo anterior, no de ningún asiento de apertura.
 *
 * Lo que decide en qué bloque cae cada cuenta es su TIPO, no su código: ingresos y
 * gastos arriba, activo y pasivo abajo justificando el saldo. Por eso el informe vale
 * igual para un plan de cuentas que no sea el de comunidades.
 *
 * El cuadre es el de la partida doble: saldo anterior + ingresos − gastos = saldo final,
 * y el saldo final es la suma de la justificación. Si algún día se registran asientos de
 * cierre habrá que sacarlos de aquí, porque llevan los ingresos y los gastos a cero.
 */
class Lista extends ListaComponent
{
    use ConEmpresaContableActiva;
    use ConRangoContable;

    /** Las fechas no filtran filas: definen el informe entero. */
    protected function filtroDesde(): array
    {
        return [
            'clave'    => 'desde',
            'etiqueta' => __('Desde'),
            'tipo'     => 'fecha',
            'aplicar'  => fn ($query, $valor) => $query,
        ];
    }

    protected function filtroHasta(): array
    {
        return [
            'clave'    => 'hasta',
            'etiqueta' => __('Hasta'),
            'tipo'     => 'fecha',
            'aplicar'  => fn ($query, $valor) => $query,
        ];
    }

    public function definicionesFiltro(): array
    {
        return [
            $this->filtroDesde(),
            $this->filtroHasta(),
        ];
    }

    /** Las columnas del informe: un mes por columna, del primero del rango al último. */
    private function meses(string $desde, string $hasta): array
    {
        $mes    = Carbon::parse($desde)->startOfMonth();
        $ultimo = Carbon::parse($hasta)->startOfMonth();
        $meses  = [];

        while ($mes <= $ultimo) {
            $meses[$mes->format('Y-m')] = Str::upper($mes->translatedFormat('M/Y'));
            $mes->addMonth();
        }

        return $meses;
    }

    /**
     * Pasa las filas de «una cuenta y un mes» a «una cuenta con todos sus meses», que es
     * como se lee el informe. El signo se endereza aquí: un ingreso es haber − debe y un
     * gasto debe − haber, para que los dos bloques se lean en positivo.
     *
     * @return array{filas: array<int, array<string, mixed>>, totales: array<string, int>, total: int}
     */
    private function pivotar(Collection $movimientos, array $meses, bool $deudora): array
    {
        $filas   = [];
        $totales = array_fill_keys(array_keys($meses), 0);

        foreach ($movimientos as $movimiento) {
            $importe = $deudora
                ? $movimiento->debe - $movimiento->haber
                : $movimiento->haber - $movimiento->debe;

            $filas[$movimiento->id] ??= [
                'codigo' => $movimiento->codigo,
                'nombre' => $movimiento->nombre,
                'meses'  => array_fill_keys(array_keys($meses), 0),
                'total'  => 0,
            ];

            $filas[$movimiento->id]['meses'][$movimiento->mes] += $importe;
            $filas[$movimiento->id]['total']                   += $importe;
            $totales[$movimiento->mes]                         += $importe;
        }

        return [
            'filas'   => array_values($filas),
            'totales' => $totales,
            'total'   => array_sum($totales),
        ];
    }

    public function render()
    {
        $saldos            = app(SaldosContablesService::class);
        $empresaContableId = $this->empresaContableActual()?->id ?? 0;
        $desde             = $this->desde();
        $hasta             = $this->hasta();

        // Sin las dos fechas no hay informe: ni meses que pintar ni saldo del que partir.
        if (! $desde || ! $hasta || $hasta < $desde) {
            return view('livewire.movimientos-contables.lista', ['rango' => false]);
        }

        $meses    = $this->meses($desde, $hasta);
        $balance  = [TipoCuentaContable::ACTIVO, TipoCuentaContable::PASIVO];
        $ingresos = $this->pivotar(
            $saldos->movimientosPorMes($empresaContableId, [TipoCuentaContable::INGRESO], $desde, $hasta),
            $meses,
            deudora: false,
        );
        $gastos = $this->pivotar(
            $saldos->movimientosPorMes($empresaContableId, [TipoCuentaContable::GASTO], $desde, $hasta),
            $meses,
            deudora: true,
        );

        return view('livewire.movimientos-contables.lista', [
            'rango'         => true,
            'meses'         => $meses,
            'ingresos'      => $ingresos,
            'gastos'        => $gastos,
            // El saldo de la víspera: lo que había el día antes de empezar el rango.
            'saldoAnterior' => $saldos->totalSaldos($empresaContableId, $balance, Carbon::parse($desde)->subDay()->format('Y-m-d')),
            'justificacion' => $saldos->saldosPorCuenta($empresaContableId, $balance, $hasta),
            'saldoFinal'    => $saldos->totalSaldos($empresaContableId, $balance, $hasta),
        ]);
    }
}
