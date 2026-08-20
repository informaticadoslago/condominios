<?php

namespace App\Services\MovimientosBancarios;

use App\Models\CuentaBancaria;
use App\Models\MovimientoBancario;
use Illuminate\Support\Str;

/**
 * Vuelca en bruto el extracto del banco (CSV o Q43/Norma 43, el formato se reconoce
 * solo) en movimientos_bancarios: todas las filas, sin clasificar. El hash de cada
 * fila se calcula solo con sus propios valores, así que si dos ficheros se solapan en
 * fechas, o se reprocesa el mismo fichero, las filas repetidas simplemente se saltan.
 */
final class ImportarMovimientosBancariosCsv
{
    /**
     * @return array{
     *     cuentaBancaria: ?CuentaBancaria,
     *     total: int,
     *     importados: int,
     *     saltados: int,
     *     error: ?string,
     * }
     */
    public function importar(string $contenido): array
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
            return $this->importarCsv($lineas);
        }

        if (Str::startsWith($primera, '11') && strlen($primera) >= 20 && ctype_digit(substr($primera, 0, 20))) {
            return $this->importarQ43($lineas);
        }

        return $this->error(__('El fichero no es un CSV ni un Q43/Norma 43 reconocible (primera línea: :linea).', ['linea' => $primera]));
    }

    private function importarCsv(array $lineas): array
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

        $movimientos = [];
        foreach ($filas as $fila) {
            $saldo = trim($fila['SALDO'] ?? '');

            $movimientos[] = [
                'fecha_valor'     => trim($fila['F. VALOR'] ?? ''),
                'fecha_contable'  => trim($fila['F. CONTABLE'] ?? '') ?: null,
                'fecha_operacion' => trim($fila['F. OPERACIÓN'] ?? '') ?: null,
                'tipo_operacion'  => trim($fila['TIPO OPERACIÓN'] ?? ''),
                'descripcion'     => trim($fila['DESCRIPCIÓN'] ?? '') ?: null,
                'referencia'      => trim($fila['REFERENCIA'] ?? '') ?: null,
                'importe'         => round($this->aImporte($fila['IMPORTE'] ?? '0'), 2),
                'saldo'           => $saldo !== '' ? round($this->aImporte($saldo), 2) : null,
                'divisa'          => trim($fila['DIVISA'] ?? '') ?: null,
            ];
        }

        return $this->guardar($cuentaBancaria, $movimientos);
    }

    /**
     * Registro 11 (cabecera de cuenta): posiciones 3-6 entidad, 7-10 oficina, 11-20
     * cuenta. No traen dígitos de control, así que se busca el IBAN por coincidencia
     * de esos tres trozos, dejando los 2+2 dígitos de control como comodín.
     */
    private function importarQ43(array $lineas): array
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

        $movimientos = [];
        foreach ($this->filasDesdeQ43($lineas) as $fila) {
            $movimientos[] = [
                'fecha_valor'     => $fila['fecha_valor'],
                'fecha_contable'  => null,
                'fecha_operacion' => null,
                'tipo_operacion'  => $fila['tipo_operacion'],
                'descripcion'     => $fila['descripcion'] ?: null,
                'referencia'      => null,
                'importe'         => $fila['importe'],
                'saldo'           => null,
                'divisa'          => null,
            ];
        }

        return $this->guardar($cuentaBancaria, $movimientos);
    }

    /**
     * Cada línea "22" es un movimiento; las "23xx" que le siguen son continuación de su
     * texto libre (se concatenan tal cual, sin espacio: una descripción cortada a mitad
     * de palabra sigue exactamente donde la dejó la línea anterior). Cualquier otro tipo
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
        $fecha = '20'.substr($actual['fecha_valor'], 0, 2).'-'.substr($actual['fecha_valor'], 2, 2).'-'.substr($actual['fecha_valor'], 4, 2);
        $euros = $actual['importe_centimos'] / 100;
        $signo = $actual['signo'] === '1' ? -1 : 1;

        return [
            'fecha_valor'    => $fecha,
            'tipo_operacion' => $actual['tipo_operacion'],
            'descripcion'    => rtrim($actual['descripcion']),
            'importe'        => round($euros * $signo, 2),
        ];
    }

    /**
     * Inserta lo que no exista ya (por hash) para esta cuenta. El índice único
     * (cuenta_bancaria_id, hash) es quien de verdad lo garantiza; insertOrIgnore evita
     * que una carrera entre dos importaciones simultáneas reviente la petición.
     *
     * @param  array<int, array{fecha_valor:string, fecha_contable:?string, fecha_operacion:?string, tipo_operacion:string, descripcion:?string, referencia:?string, importe:float, saldo:?float, divisa:?string}>  $movimientos
     */
    private function guardar(CuentaBancaria $cuentaBancaria, array $movimientos): array
    {
        $total = count($movimientos);

        $filas = collect($movimientos)
            ->map(fn ($m) => [
                ...$m,
                'cuenta_bancaria_id' => $cuentaBancaria->id,
                'hash'               => $this->hash($m),
                'created_at'         => now(),
                'updated_at'         => now(),
            ])
            // Dentro del propio fichero puede haber líneas repetidas letra a letra (dos
            // movimientos idénticos el mismo día): el índice único las pararía igual,
            // pero así el recuento de "importados" sale bien a la primera.
            ->unique('hash')
            ->values();

        $importados = 0;
        foreach ($filas->chunk(500) as $trozo) {
            $importados += MovimientoBancario::insertOrIgnore($trozo->all());
        }

        return [
            'cuentaBancaria' => $cuentaBancaria,
            'total'          => $total,
            'importados'     => $importados,
            'saltados'       => $total - $importados,
            'error'          => null,
        ];
    }

    private function hash(array $movimiento): string
    {
        return hash('sha256', implode('|', [
            $movimiento['fecha_valor'],
            $movimiento['fecha_contable'],
            $movimiento['fecha_operacion'],
            $movimiento['tipo_operacion'],
            $movimiento['descripcion'],
            $movimiento['referencia'],
            number_format($movimiento['importe'], 2, '.', ''),
            $movimiento['saldo'] !== null ? number_format($movimiento['saldo'], 2, '.', '') : '',
            $movimiento['divisa'],
        ]));
    }

    /** "1.543,58" / "-718,51" → float, formato español (punto de miles, coma decimal). */
    private function aImporte(string $raw): float
    {
        $raw = str_replace('.', '', trim($raw));
        $raw = str_replace(',', '.', $raw);

        return (float) $raw;
    }

    private function error(string $mensaje): array
    {
        return [
            'cuentaBancaria' => null,
            'total'          => 0,
            'importados'     => 0,
            'saltados'       => 0,
            'error'          => $mensaje,
        ];
    }
}
