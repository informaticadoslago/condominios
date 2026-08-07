<?php

namespace App\Services\Contabilidad;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lo que suma una cuenta: saldo a una fecha y sumas del debe y del haber entre dos.
 *
 * Es la consulta sobre la que se apoyan todos los informes —el mayor, el balance de
 * sumas y saldos y el de movimientos—, y por eso vive aparte de cualquiera de ellos.
 *
 * Todo en céntimos enteros, como el resto del módulo. El signo es el de la partida
 * doble: positivo si la cuenta es deudora (bancos con dinero, propietario que debe),
 * negativo si es acreedora (proveedor pendiente de pago, cuentas de ingreso).
 *
 * No hace falta filtrar por empresa contable: una cuenta pertenece a una sola, así que
 * sus apuntes solo pueden venir de asientos de esa misma empresa.
 *
 * El acumulado no mira ejercicios: suma todo lo anterior a la fecha, venga del ejercicio
 * que venga. Es lo que hace que el saldo inicial de un rango a mitad de año salga solo,
 * sin arrastres que calcular aparte.
 */
final class SaldosContablesService
{
    /** Saldo de la cuenta contando todo lo apuntado hasta $hasta inclusive (sin fecha, todo). */
    public function saldo(int $cuentaContableId, ?string $hasta = null): int
    {
        return (int) $this->apuntesDe($cuentaContableId)
            ->when($hasta, fn ($query) => $query->whereDate('asiento_contables.fecha', '<=', $hasta))
            ->selectRaw('COALESCE(SUM(apunte_contables.debe - apunte_contables.haber), 0) AS saldo')
            ->value('saldo');
    }

    /**
     * Saldo con el que la cuenta llega a $fecha, sin contar ese mismo día: el saldo
     * inicial de un mayor o de un balance que empiece ahí. Sin fecha no hay nada
     * anterior que arrastrar y es cero.
     */
    public function saldoAnteriorA(int $cuentaContableId, ?string $fecha): int
    {
        if (! $fecha) {
            return 0;
        }

        return (int) $this->apuntesDe($cuentaContableId)
            ->whereDate('asiento_contables.fecha', '<', $fecha)
            ->selectRaw('COALESCE(SUM(apunte_contables.debe - apunte_contables.haber), 0) AS saldo')
            ->value('saldo');
    }

    /**
     * Debe y haber movidos en la cuenta entre las dos fechas, ambas inclusive.
     *
     * @return array{debe: int, haber: int}
     */
    public function sumas(int $cuentaContableId, ?string $desde = null, ?string $hasta = null): array
    {
        $sumas = $this->apuntesDe($cuentaContableId)
            ->when($desde, fn ($query) => $query->whereDate('asiento_contables.fecha', '>=', $desde))
            ->when($hasta, fn ($query) => $query->whereDate('asiento_contables.fecha', '<=', $hasta))
            ->selectRaw('COALESCE(SUM(apunte_contables.debe), 0) AS debe, COALESCE(SUM(apunte_contables.haber), 0) AS haber')
            ->first();

        return ['debe' => (int) $sumas->debe, 'haber' => (int) $sumas->haber];
    }

    /**
     * El balance de sumas y saldos de una empresa entre dos fechas, cuenta a cuenta: con
     * qué saldo llega cada una al primer día, qué movió en el periodo y cómo queda.
     *
     * Es una sola consulta agregada para todas las cuentas, no una por cuenta: el reparto
     * entre «lo anterior» y «lo del periodo» se hace con los CASE de dentro del SUM.
     *
     * Solo salen las cuentas que tienen algo que contar —saldo anterior o movimiento en
     * el rango—; las del plan por las que no ha pasado nunca nada no ensucian el listado.
     *
     * Devuelve el query sin ordenar ni paginar, para que quien lo pida lo remate: ordenar
     * por código, paginarlo, o envolverlo para sacar los totales (ver totalesBalance()).
     */
    public function balance(int $empresaContableId, ?string $desde = null, ?string $hasta = null): Builder
    {
        // Sin fecha de inicio no hay «anterior» que separar: todo es periodo.
        $anterior = $desde
            ? 'SUM(CASE WHEN asiento_contables.fecha < ? THEN apunte_contables.debe - apunte_contables.haber ELSE 0 END)'
            : '0';

        $delPeriodo = $desde
            ? 'CASE WHEN asiento_contables.fecha >= ? THEN apunte_contables.%s ELSE 0 END'
            : 'apunte_contables.%s';

        return DB::table('cuenta_contables')
            ->join('apunte_contables', 'apunte_contables.cuenta_contable_id', '=', 'cuenta_contables.id')
            ->join('asiento_contables', 'asiento_contables.id', '=', 'apunte_contables.asiento_contable_id')
            ->where('cuenta_contables.empresa_contable_id', $empresaContableId)
            ->when($hasta, fn ($query) => $query->whereDate('asiento_contables.fecha', '<=', $hasta))
            ->groupBy('cuenta_contables.id', 'cuenta_contables.codigo', 'cuenta_contables.nombre')
            ->havingRaw('saldo_inicial <> 0 OR debe <> 0 OR haber <> 0')
            ->selectRaw('cuenta_contables.id, cuenta_contables.codigo, cuenta_contables.nombre')
            ->selectRaw("COALESCE({$anterior}, 0) AS saldo_inicial", $desde ? [$desde] : [])
            ->selectRaw('COALESCE(SUM('.sprintf($delPeriodo, 'debe').'), 0) AS debe', $desde ? [$desde] : [])
            ->selectRaw('COALESCE(SUM('.sprintf($delPeriodo, 'haber').'), 0) AS haber', $desde ? [$desde] : [])
            ->selectRaw('COALESCE(SUM(apunte_contables.debe - apunte_contables.haber), 0) AS saldo_final');
    }

    /**
     * Los totales de ese mismo balance, sumando TODAS sus cuentas y no solo la página que
     * se esté viendo. El del saldo final tiene que dar cero: es la partida doble.
     *
     * @return array{saldo_inicial: int, debe: int, haber: int, saldo_final: int}
     */
    public function totalesBalance(int $empresaContableId, ?string $desde = null, ?string $hasta = null): array
    {
        $totales = DB::query()
            ->fromSub($this->balance($empresaContableId, $desde, $hasta), 'balance')
            ->selectRaw('COALESCE(SUM(saldo_inicial), 0) AS saldo_inicial, COALESCE(SUM(debe), 0) AS debe,'
                .' COALESCE(SUM(haber), 0) AS haber, COALESCE(SUM(saldo_final), 0) AS saldo_final')
            ->first();

        return [
            'saldo_inicial' => (int) $totales->saldo_inicial,
            'debe'          => (int) $totales->debe,
            'haber'         => (int) $totales->haber,
            'saldo_final'   => (int) $totales->saldo_final,
        ];
    }

    /**
     * Lo que movió cada cuenta de los tipos pedidos, mes a mes, entre las dos fechas.
     *
     * Una fila por cuenta y mes («2026-03»), con su debe y su haber: es la materia prima
     * del informe de movimientos, que la pivota a cuentas en filas y meses en columnas.
     * Las cuentas que no movieron nada en el rango no salen.
     *
     * @param  list<int>  $tiposCuenta  constantes de TipoCuentaContable
     */
    public function movimientosPorMes(int $empresaContableId, array $tiposCuenta, ?string $desde = null, ?string $hasta = null): Collection
    {
        return DB::table('cuenta_contables')
            ->join('apunte_contables', 'apunte_contables.cuenta_contable_id', '=', 'cuenta_contables.id')
            ->join('asiento_contables', 'asiento_contables.id', '=', 'apunte_contables.asiento_contable_id')
            ->where('cuenta_contables.empresa_contable_id', $empresaContableId)
            ->whereIn('cuenta_contables.tipo_cuenta_contable_id', $tiposCuenta)
            ->when($desde, fn ($query) => $query->whereDate('asiento_contables.fecha', '>=', $desde))
            ->when($hasta, fn ($query) => $query->whereDate('asiento_contables.fecha', '<=', $hasta))
            ->groupBy('cuenta_contables.id', 'cuenta_contables.codigo', 'cuenta_contables.nombre', 'mes')
            ->orderBy('cuenta_contables.codigo')
            ->selectRaw('cuenta_contables.id, cuenta_contables.codigo, cuenta_contables.nombre')
            ->selectRaw("DATE_FORMAT(asiento_contables.fecha, '%Y-%m') AS mes")
            ->selectRaw('SUM(apunte_contables.debe) AS debe, SUM(apunte_contables.haber) AS haber')
            ->get();
    }

    /**
     * Saldo a una fecha de las cuentas de los tipos pedidos, las que tengan algo. Con
     * activo y pasivo es la justificación del saldo: el dinero que hay en los bancos, lo
     * que deben los propietarios y lo que queda pendiente de pagar.
     *
     * @param  list<int>  $tiposCuenta  constantes de TipoCuentaContable
     */
    public function saldosPorCuenta(int $empresaContableId, array $tiposCuenta, ?string $hasta = null): Collection
    {
        return DB::table('cuenta_contables')
            ->join('apunte_contables', 'apunte_contables.cuenta_contable_id', '=', 'cuenta_contables.id')
            ->join('asiento_contables', 'asiento_contables.id', '=', 'apunte_contables.asiento_contable_id')
            ->where('cuenta_contables.empresa_contable_id', $empresaContableId)
            ->whereIn('cuenta_contables.tipo_cuenta_contable_id', $tiposCuenta)
            ->when($hasta, fn ($query) => $query->whereDate('asiento_contables.fecha', '<=', $hasta))
            ->groupBy('cuenta_contables.id', 'cuenta_contables.codigo', 'cuenta_contables.nombre')
            ->havingRaw('saldo <> 0')
            ->orderBy('cuenta_contables.codigo')
            ->selectRaw('cuenta_contables.id, cuenta_contables.codigo, cuenta_contables.nombre')
            ->selectRaw('SUM(apunte_contables.debe - apunte_contables.haber) AS saldo')
            ->get();
    }

    /**
     * Lo mismo, sumado: con activo y pasivo, el saldo de la comunidad a esa fecha —lo
     * que tiene menos lo que debe—, que es con lo que arranca un informe de movimientos.
     *
     * @param  list<int>  $tiposCuenta  constantes de TipoCuentaContable
     */
    public function totalSaldos(int $empresaContableId, array $tiposCuenta, ?string $hasta = null): int
    {
        return (int) DB::table('cuenta_contables')
            ->join('apunte_contables', 'apunte_contables.cuenta_contable_id', '=', 'cuenta_contables.id')
            ->join('asiento_contables', 'asiento_contables.id', '=', 'apunte_contables.asiento_contable_id')
            ->where('cuenta_contables.empresa_contable_id', $empresaContableId)
            ->whereIn('cuenta_contables.tipo_cuenta_contable_id', $tiposCuenta)
            ->when($hasta, fn ($query) => $query->whereDate('asiento_contables.fecha', '<=', $hasta))
            ->selectRaw('COALESCE(SUM(apunte_contables.debe - apunte_contables.haber), 0) AS saldo')
            ->value('saldo');
    }

    /** Los apuntes de una cuenta, ya unidos a su asiento para poder filtrar por fecha. */
    private function apuntesDe(int $cuentaContableId)
    {
        return DB::table('apunte_contables')
            ->join('asiento_contables', 'asiento_contables.id', '=', 'apunte_contables.asiento_contable_id')
            ->where('apunte_contables.cuenta_contable_id', $cuentaContableId);
    }
}
