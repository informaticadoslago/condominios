<?php

namespace App\Services\Contabilidad;

use App\Models\TipoCuentaContable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Arma el informe de movimientos entre dos fechas: los ingresos y los gastos mes a mes,
 * el saldo del que se parte y la justificación del saldo al que se llega.
 *
 * Lo usan la pantalla (MovimientosContables\Lista) y su PDF: los dos tienen que contar
 * exactamente lo mismo, así que el cálculo vive aquí y no en ninguno de los dos.
 *
 * El cuadre es el de la partida doble: saldo anterior + ingresos − gastos = saldo final,
 * y el saldo final es la suma de la justificación.
 */
class InformeMovimientos
{
    public function __construct(private SaldosContablesService $saldos)
    {
    }

    /**
     * @return array{meses: array<string, string>, ingresos: array, gastos: array,
     *               saldoAnterior: int, justificacion: Collection, saldoFinal: int}
     */
    public function generar(int $empresaContableId, string $desde, string $hasta): array
    {
        $meses   = $this->meses($desde, $hasta);
        $balance = [TipoCuentaContable::ACTIVO, TipoCuentaContable::PASIVO];

        return [
            'meses'    => $meses,
            'ingresos' => $this->pivotar(
                $this->saldos->movimientosPorMes($empresaContableId, [TipoCuentaContable::INGRESO], $desde, $hasta),
                $meses,
                deudora: false,
            ),
            'gastos' => $this->pivotar(
                $this->saldos->movimientosPorMes($empresaContableId, [TipoCuentaContable::GASTO], $desde, $hasta),
                $meses,
                deudora: true,
            ),
            // El saldo de la víspera: lo que había el día antes de empezar el rango.
            'saldoAnterior' => $this->saldos->totalSaldos($empresaContableId, $balance, Carbon::parse($desde)->subDay()->format('Y-m-d')),
            'justificacion' => $this->saldos->saldosPorCuenta($empresaContableId, $balance, $hasta),
            'saldoFinal'    => $this->saldos->totalSaldos($empresaContableId, $balance, $hasta),
        ];
    }

    /**
     * Las columnas del informe: un mes por columna, del primero del rango al último.
     *
     * Dentro de un mismo año la cabecera va sin él (ENE, FEB…): con doce columnas,
     * repetir «/2026» doce veces solo gasta ancho. En un rango a caballo entre dos años
     * sí hace falta para no confundir enero con enero.
     */
    private function meses(string $desde, string $hasta): array
    {
        $mes     = Carbon::parse($desde)->startOfMonth();
        $ultimo  = Carbon::parse($hasta)->startOfMonth();
        $formato = $mes->year === $ultimo->year ? 'M' : 'M/Y';
        $meses   = [];

        while ($mes <= $ultimo) {
            $meses[$mes->format('Y-m')] = Str::upper($mes->translatedFormat($formato));
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
}
