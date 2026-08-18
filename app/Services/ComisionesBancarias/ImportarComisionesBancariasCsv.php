<?php

namespace App\Services\ComisionesBancarias;

use App\Models\ComisionBancaria;
use App\Models\Comunidad;
use App\Models\CuentaBancaria;
use App\Models\EjercicioContable;
use App\Models\TipoComisionBancaria;
use App\Models\TipoMovimientoBancario;
use Illuminate\Support\Str;

/**
 * Lee el extracto de movimientos del banco —en CSV o en Q43/Norma 43, el fichero se
 * reconoce solo— y separa sus filas en tres cajones: candidatas a importar, ya
 * importadas antes, y descartadas porque no son un tipo que nos interesa aquí.
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
        // Los ficheros de banca electrónica suelen llevar BOM UTF-8: sin quitarlo, la
        // primera línea no empieza "de verdad" por ES ni por 11, aunque se vea igual.
        $contenido = ltrim($contenido, "\xEF\xBB\xBF");

        $lineas = preg_split('/\r\n|\r|\n/', $contenido);
        $lineas = array_values(array_filter($lineas, fn ($l) => trim($l) !== ''));

        if (empty($lineas)) {
            return $this->error(__('El fichero está vacío.'));
        }

        $primera = trim($lineas[0]);

        if (Str::startsWith($primera, 'ES') && strlen($primera) >= 20) {
            return $this->analizarCsv($lineas);
        }

        if (Str::startsWith($primera, '11') && strlen($primera) >= 20 && ctype_digit(substr($primera, 0, 20))) {
            return $this->analizarQ43($lineas);
        }

        return $this->error(__('El fichero no es un CSV ni un Q43/Norma 43 reconocible (primera línea: :linea).', ['linea' => $primera]));
    }

    private function analizarCsv(array $lineas): array
    {
        $iban = trim($lineas[0]);
        $cuentaBancaria = CuentaBancaria::where('iban', $iban)->first();

        if (! $cuentaBancaria) {
            return $this->error(__('No hay ninguna cuenta bancaria con el IBAN :iban', ['iban' => $iban]));
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

        return $this->clasificar($cuentaBancaria, $filas);
    }

    /**
     * Registro 11 (cabecera de cuenta): posiciones 3-6 entidad, 7-10 oficina, 11-20
     * cuenta. No traen dígitos de control, así que se busca el IBAN por coincidencia
     * de esos tres trozos, dejando los 2+2 dígitos de control como comodín.
     */
    private function analizarQ43(array $lineas): array
    {
        $cabecera = $lineas[0];
        $entidad  = substr($cabecera, 2, 4);
        $oficina  = substr($cabecera, 6, 4);
        $cuenta   = substr($cabecera, 10, 10);

        $cuentaBancaria = CuentaBancaria::where('iban', 'like', "ES__{$entidad}{$oficina}__{$cuenta}")->first();

        if (! $cuentaBancaria) {
            return $this->error(__('No hay ninguna cuenta bancaria que case con entidad :entidad, oficina :oficina y cuenta :cuenta.', [
                'entidad' => $entidad, 'oficina' => $oficina, 'cuenta' => $cuenta,
            ]));
        }

        return $this->clasificar($cuentaBancaria, $this->filasDesdeQ43($lineas));
    }

    /**
     * Cada línea "22" es un movimiento; las "23xx" que le siguen son continuación de su
     * texto libre (se concatenan tal cual, sin espacio: una FRA cortada a mitad de
     * palabra sigue exactamente donde la dejó la línea anterior). Cualquier otro tipo
     * de registro cierra el movimiento que estuviera abierto.
     */
    private function filasDesdeQ43(array $lineas): array
    {
        $filas  = [];
        $actual = null;

        foreach ($lineas as $linea) {
            $tipoRegistro = substr($linea, 0, 2);

            if ($tipoRegistro === '22') {
                if ($actual !== null) {
                    $filas[] = $this->cerrarFilaQ43($actual);
                }

                $actual = [
                    'fecha_valor'      => substr($linea, 16, 6),
                    'signo'            => substr($linea, 27, 1),
                    'importe_centimos' => (int) substr($linea, 28, 14),
                    'tipo_operacion'   => trim(substr($linea, 52)),
                    'descripcion'      => '',
                ];

                continue;
            }

            if ($tipoRegistro === '23' && $actual !== null) {
                $subtipo   = substr($linea, 2, 2);
                $contenido = substr($linea, 4);
                $actual['descripcion'] .= $subtipo === '01' ? ltrim($contenido) : $contenido;

                continue;
            }

            if ($actual !== null) {
                $filas[] = $this->cerrarFilaQ43($actual);
                $actual = null;
            }
        }

        if ($actual !== null) {
            $filas[] = $this->cerrarFilaQ43($actual);
        }

        return $filas;
    }

    private function cerrarFilaQ43(array $actual): array
    {
        $fecha  = '20'.substr($actual['fecha_valor'], 0, 2).'-'.substr($actual['fecha_valor'], 2, 2).'-'.substr($actual['fecha_valor'], 4, 2);
        $euros  = $actual['importe_centimos'] / 100;
        $signo  = $actual['signo'] === '1' ? '-' : '';

        return [
            'F. VALOR'       => $fecha,
            'TIPO OPERACIÓN' => $actual['tipo_operacion'],
            'DESCRIPCIÓN'    => rtrim($actual['descripcion']),
            'IMPORTE'        => $signo.number_format($euros, 2, ',', ''),
        ];
    }

    /**
     * A partir de aquí es indiferente si las filas vinieron de un CSV o de un Q43:
     * misma clasificación, mismo emparejado por FRA, misma comprobación de duplicados.
     *
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

        usort($candidatas, fn ($a, $b) => $a['fecha'] <=> $b['fecha']);

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
