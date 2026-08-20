<?php

namespace App\Services\ComisionesBancarias;

use App\Models\ComisionBancaria;
use App\Models\Comunidad;
use App\Models\CuentaBancaria;
use App\Models\EjercicioContable;
use App\Models\MovimientoBancario;
use App\Models\TipoComisionBancaria;
use App\Models\TipoMovimientoBancario;
use Illuminate\Support\Str;

/**
 * Clasifica los movimientos ya volcados en movimientos_bancarios (el extracto lo lee
 * ImportarMovimientosBancariosCsv, aquí ya no se sube ningún fichero) en tres cajones:
 * candidatas a comisión, ya importadas antes, y descartadas porque no son un tipo que
 * nos interesa aquí.
 *
 * No escribe nada: solo prepara las propuestas. El alta de verdad la hace
 * RegistrarComisionBancariaService, fila a fila, cuando el usuario confirma.
 */
final class ClasificarComisionesDesdeMovimientos
{
    /**
     * @return array{
     *     cuentaBancaria: ?CuentaBancaria,
     *     candidatas: array<int, array>,
     *     yaProcesadas: array<int, array>,
     *     descartadas: array<int, array>,
     *     error: ?string,
     * }
     */
    public function analizar(int $cuentaBancariaId): array
    {
        $cuentaBancaria = CuentaBancaria::find($cuentaBancariaId);

        if (! $cuentaBancaria) {
            return $this->error(__('Cuenta bancaria no encontrada.'));
        }

        // Mismas claves que traía el CSV, para no tocar clasificar(): el importe se
        // devuelve al formato español porque aImporte() espera esa cadena. Más moderno
        // primero, para revisar las descartadas de más arriba a abajo; ID va aparte,
        // solo lo usa filaDescartada() para poder convertir esa línea a mano.
        $filas = MovimientoBancario::where('cuenta_bancaria_id', $cuentaBancaria->id)
            ->orderByDesc('fecha_valor')
            ->get()
            ->map(fn (MovimientoBancario $m) => [
                'ID'             => $m->id,
                'F. VALOR'       => $m->fecha_valor->format('Y-m-d'),
                'TIPO OPERACIÓN' => $m->tipo_operacion,
                'DESCRIPCIÓN'    => $m->descripcion ?? '',
                'IMPORTE'        => number_format((float) $m->importe, 2, ',', '.'),
            ])
            ->all();

        return $this->clasificar($cuentaBancaria, $filas);
    }

    /**
     * @param  array<int, array{"F. VALOR": string, "TIPO OPERACIÓN": string, "DESCRIPCIÓN": string, "IMPORTE": string}>  $filas
     */
    private function clasificar(CuentaBancaria $cuentaBancaria, array $filas): array
    {
        if (! $cuentaBancaria->entidad_bancaria_id) {
            return $this->error(__('Esa cuenta bancaria no tiene entidad bancaria asignada.'));
        }

        $tipos = TipoMovimientoBancario::where('entidad_bancaria_id', $cuentaBancaria->entidad_bancaria_id)->get();

        $candidatasMantenimiento = [];
        $porFra = [];
        $descartadas = [];

        foreach ($filas as $fila) {
            $tipoOperacion = trim($fila['TIPO OPERACIÓN'] ?? '');
            $descripcion   = trim($fila['DESCRIPCIÓN'] ?? '');

            $tipo = $tipos->first(function (TipoMovimientoBancario $t) use ($tipoOperacion, $descripcion) {
                if ($t->tipo_operacion !== $tipoOperacion) {
                    return false;
                }

                return $t->prefijo_descripcion === null || Str::startsWith($descripcion, $t->prefijo_descripcion);
            });

            if (! $tipo) {
                $descartadas[] = $this->filaDescartada($fila, __('No es un tipo de movimiento que se importe aquí.'));

                continue;
            }

            $fecha   = trim($fila['F. VALOR'] ?? '');
            $importe = abs($this->aImporte($fila['IMPORTE'] ?? '0'));

            // Remesa y devolución llevan FRA y se agrupan igual (comisión + IVA); el
            // mantenimiento no trae FRA en el extracto y va suelto.
            if (in_array($tipo->codigo, [TipoComisionBancaria::REMESA, TipoComisionBancaria::DEVOLUCION], true)) {
                $fra = $this->extraerFra($descripcion);

                if (! $fra) {
                    $descartadas[] = $this->filaDescartada($fila, __('No se ha encontrado el nº de FRA en la descripción.'));

                    continue;
                }

                $porFra[$fra]['fecha'] ??= $fecha;
                $porFra[$fra]['codigo'] ??= $tipo->codigo;
                $porFra[$fra]['filas'][] = $fila;

                if (Str::contains($tipoOperacion, 'I.V.A.')) {
                    $porFra[$fra]['iva'] = $importe;
                } else {
                    $porFra[$fra]['comision'] = $importe;
                }
            } else {
                $candidatasMantenimiento[] = [
                    'fecha'      => $fecha,
                    'referencia' => null,
                    'codigo'     => TipoComisionBancaria::MANTENIMIENTO,
                    'concepto'   => $descripcion,
                    'lineas'     => [['concepto' => $descripcion, 'importe' => $importe]],
                    'filas'      => [$fila],
                ];
            }
        }

        $candidatasPorFra = [];
        foreach ($porFra as $fra => $grupo) {
            if (! isset($grupo['comision']) || ! isset($grupo['iva'])) {
                foreach ($grupo['filas'] as $fila) {
                    $descartadas[] = $this->filaDescartada($fila, __('FRA :fra incompleta en el fichero (falta la comisión o el IVA).', ['fra' => $fra]));
                }

                continue;
            }

            $candidatasPorFra[] = [
                'fecha'      => $grupo['fecha'],
                'referencia' => $fra,
                'codigo'     => $grupo['codigo'],
                'concepto'   => $grupo['codigo'] === TipoComisionBancaria::DEVOLUCION
                    ? __('Comisión de devolución :fecha', ['fecha' => $grupo['fecha']])
                    : __('Liquidación remesa :fecha', ['fecha' => $grupo['fecha']]),
                'lineas' => [
                    ['concepto' => __('Comisión'), 'importe' => $grupo['comision']],
                    ['concepto' => __('IVA comisión'), 'importe' => $grupo['iva']],
                ],
                'filas' => $grupo['filas'],
            ];
        }

        $todasLasCandidatas = [...$candidatasPorFra, ...$candidatasMantenimiento];

        $empresaId = $this->empresaContableId($cuentaBancaria);

        $candidatas = [];
        $yaProcesadas = [];

        foreach ($todasLasCandidatas as $candidata) {
            if ($this->yaProcesada($cuentaBancaria->id, $candidata)) {
                $yaProcesadas[] = $candidata;
            } else {
                $candidata['fuera_ejercicio'] = ! $this->enEjercicioAbierto($empresaId, $candidata['fecha']);
                $candidatas[] = $candidata;
            }
        }

        usort($candidatas, fn ($a, $b) => $b['fecha'] <=> $a['fecha']);

        return [
            'cuentaBancaria' => $cuentaBancaria,
            'candidatas'     => $candidatas,
            'yaProcesadas'   => $yaProcesadas,
            'descartadas'    => $descartadas,
            'error'          => null,
        ];
    }

    private function empresaContableId(CuentaBancaria $cuentaBancaria): ?int
    {
        $titular = $cuentaBancaria->titular;

        return $titular instanceof Comunidad ? $titular->empresa_contable_id : null;
    }

    /**
     * Si en su día no se importó una fila y hoy cae fuera del ejercicio en curso, lo más
     * probable es que ya se metiera a mano entonces: se enseña aparte y sin marcar, no
     * se da por hecho que haya que importarla.
     */
    private function enEjercicioAbierto(?int $empresaId, string $fecha): bool
    {
        if ($empresaId === null) {
            return false;
        }

        return EjercicioContable::where('empresa_contable_id', $empresaId)
            ->where('fecha_inicio', '<=', $fecha)
            ->where('fecha_fin', '>=', $fecha)
            ->where('cerrado', false)
            ->exists();
    }

    private function yaProcesada(int $cuentaBancariaId, array $candidata): bool
    {
        if ($candidata['referencia'] !== null) {
            return ComisionBancaria::where('cuenta_bancaria_id', $cuentaBancariaId)
                ->where('referencia', $candidata['referencia'])
                ->exists();
        }

        $importeTotal = array_sum(array_column($candidata['lineas'], 'importe'));

        return ComisionBancaria::where('cuenta_bancaria_id', $cuentaBancariaId)
            ->where('fecha', $candidata['fecha'])
            ->whereHas('tipoComisionBancaria', fn ($q) => $q->where('codigo', $candidata['codigo']))
            ->with('lineas')
            ->get()
            ->contains(fn (ComisionBancaria $c) => round((float) $c->lineas->sum('importe'), 2) === round($importeTotal, 2));
    }

    /** Nº de FRA dentro de "LIQ. REM. 31-07-2026 FRA BI 50252026071000345" (o FRA IVA). */
    private function extraerFra(string $descripcion): ?string
    {
        return preg_match('/FRA\s+(?:BI|IVA)\s+(\S+)/i', $descripcion, $m) ? $m[1] : null;
    }

    /** "1.543,58" / "-718,51" → float, formato español (punto de miles, coma decimal). */
    private function aImporte(string $raw): float
    {
        $raw = str_replace('.', '', trim($raw));
        $raw = str_replace(',', '.', $raw);

        return (float) $raw;
    }

    private function filaDescartada(array $fila, string $motivo): array
    {
        return [
            'id'       => $fila['ID'] ?? null,
            'fecha'    => $fila['F. VALOR'] ?? null,
            'tipo'     => $fila['TIPO OPERACIÓN'] ?? null,
            'concepto' => $fila['DESCRIPCIÓN'] ?? null,
            'importe'  => $fila['IMPORTE'] ?? null,
            'motivo'   => $motivo,
        ];
    }

    private function error(string $mensaje): array
    {
        return [
            'cuentaBancaria' => null,
            'candidatas'     => [],
            'yaProcesadas'   => [],
            'descartadas'    => [],
            'error'          => $mensaje,
        ];
    }
}
