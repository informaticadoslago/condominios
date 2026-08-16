<?php

namespace App\Services\ComisionesBancarias;

use App\Models\ComisionBancaria;
use App\Models\CuentaBancaria;
use App\Models\TipoComisionBancaria;
use App\Models\TipoMovimientoBancario;
use Illuminate\Support\Str;

/**
 * Lee el extracto de movimientos en CSV que exporta el banco (mismo dato que el Q43) y
 * separa sus filas en tres cajones: candidatas a importar, ya importadas antes, y
 * descartadas porque no son un tipo que nos interesa aquí.
 *
 * No escribe nada: solo prepara las propuestas. El alta de verdad la hace
 * RegistrarComisionBancariaService, fila a fila, cuando el usuario confirma.
 */
final class ImportarComisionesBancariasCsv
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
    public function analizar(string $contenido): array
    {
        $lineas = preg_split('/\r\n|\r|\n/', $contenido);
        $lineas = array_values(array_filter($lineas, fn ($l) => trim($l) !== ''));

        if (empty($lineas)) {
            return $this->error(__('El fichero está vacío.'));
        }

        $iban = trim($lineas[0]);
        $cuentaBancaria = CuentaBancaria::where('iban', $iban)->first();

        if (! $cuentaBancaria) {
            return $this->error(__('No hay ninguna cuenta bancaria con el IBAN :iban', ['iban' => $iban]));
        }

        if (! $cuentaBancaria->entidad_bancaria_id) {
            return $this->error(__('Esa cuenta bancaria no tiene entidad bancaria asignada.'));
        }

        $indiceCabecera = null;
        foreach ($lineas as $i => $linea) {
            if (Str::startsWith(trim($linea), 'F. VALOR')) {
                $indiceCabecera = $i;
                break;
            }
        }

        if ($indiceCabecera === null) {
            return $this->error(__('No se encuentra la fila de cabecera (F. VALOR;F. CONTABLE;...).'));
        }

        $cabecera = str_getcsv($lineas[$indiceCabecera], ';');
        $filas = [];
        foreach (array_slice($lineas, $indiceCabecera + 1) as $linea) {
            $valores = str_getcsv($linea, ';');
            if (count($valores) < count($cabecera)) {
                continue;
            }
            $filas[] = array_combine($cabecera, array_slice($valores, 0, count($cabecera)));
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

            if ($tipo->codigo === TipoComisionBancaria::REMESA) {
                $fra = $this->extraerFra($descripcion);

                if (! $fra) {
                    $descartadas[] = $this->filaDescartada($fila, __('No se ha encontrado el nº de FRA en la descripción.'));

                    continue;
                }

                $porFra[$fra]['fecha'] ??= $fecha;
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

        $candidatasRemesa = [];
        foreach ($porFra as $fra => $grupo) {
            if (! isset($grupo['comision']) || ! isset($grupo['iva'])) {
                foreach ($grupo['filas'] as $fila) {
                    $descartadas[] = $this->filaDescartada($fila, __('FRA :fra incompleta en el fichero (falta la comisión o el IVA).', ['fra' => $fra]));
                }

                continue;
            }

            $candidatasRemesa[] = [
                'fecha'      => $grupo['fecha'],
                'referencia' => $fra,
                'codigo'     => TipoComisionBancaria::REMESA,
                'concepto'   => __('Liquidación remesa :fecha', ['fecha' => $grupo['fecha']]),
                'lineas'     => [
                    ['concepto' => __('Comisión'), 'importe' => $grupo['comision']],
                    ['concepto' => __('IVA comisión'), 'importe' => $grupo['iva']],
                ],
                'filas' => $grupo['filas'],
            ];
        }

        $todasLasCandidatas = [...$candidatasRemesa, ...$candidatasMantenimiento];

        $candidatas = [];
        $yaProcesadas = [];

        foreach ($todasLasCandidatas as $candidata) {
            if ($this->yaProcesada($cuentaBancaria->id, $candidata)) {
                $yaProcesadas[] = $candidata;
            } else {
                $candidatas[] = $candidata;
            }
        }

        usort($candidatas, fn ($a, $b) => $a['fecha'] <=> $b['fecha']);

        return [
            'cuentaBancaria' => $cuentaBancaria,
            'candidatas'     => $candidatas,
            'yaProcesadas'   => $yaProcesadas,
            'descartadas'    => $descartadas,
            'error'          => null,
        ];
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
