<?php

namespace App\Services\Horarios;

/**
 * Saca las notas de un listado de calificaciones copiado de la pantalla y pegado como
 * texto, un dato por línea:
 *
 *   RBRaúl Benítez Ribadomar     ← «Nombre Apellidos», a veces con las iniciales delante
 *   rbenrib03@gmail.com          ← correo
 *   -                            ← una línea por columna de notas: «-» o un número
 *    7,00
 *   ...
 *
 * No trae cabeceras: las columnas se numeran desde 1 y las notas son sobre 10. Cada alumno
 * es la línea anterior a un correo, y sus notas, las líneas de detrás mientras sean «-» o
 * un número; así la media del final no cuenta.
 */
class LectorNotasListado
{
    public const MAXIMO = 10;

    private const CORREO = '/^\S+@\S+\.\S+$/u';

    /**
     * @return array{columnas: array<int, string>, filas: array<int, array{texto: string, valores: array<int, ?float>}>}
     *                                                                                                                   'columnas': las que traen alguna nota, con sus primeras notas como pista.
     */
    public function leer(string $texto): array
    {
        $lineas = array_values(array_filter(
            array_map(fn ($linea) => trim(preg_replace('/\s+/u', ' ', $linea)), preg_split('/\R/u', $texto)),
            fn ($linea) => $linea !== ''
        ));

        $filas = [];
        foreach ($lineas as $i => $linea) {
            if ($i === 0 || ! preg_match(self::CORREO, $linea) || preg_match(self::CORREO, $lineas[$i - 1])) {
                continue;
            }

            $valores = [];
            for ($j = $i + 1; isset($lineas[$j]) && $this->esValor($lineas[$j]); $j++) {
                // La primera columna es la 1.
                $valores[$j - $i] = $lineas[$j] === '-' ? null : (float) str_replace(',', '.', $lineas[$j]);
            }

            $filas[] = ['texto' => $lineas[$i - 1], 'valores' => $valores];
        }

        $columnas = [];
        foreach ($filas as $fila) {
            foreach ($fila['valores'] as $columna => $valor) {
                if ($valor !== null) {
                    $columnas[$columna][] = str_replace('.', ',', (string) $valor);
                }
            }
        }
        ksort($columnas);

        return [
            'columnas' => array_map(fn (array $notas) => implode(' · ', array_slice($notas, 0, 3)).(count($notas) > 3 ? '…' : ''), $columnas),
            'filas'    => $filas,
        ];
    }

    private function esValor(string $linea): bool
    {
        return $linea === '-' || preg_match('/^\d+([.,]\d+)?$/', $linea);
    }
}
