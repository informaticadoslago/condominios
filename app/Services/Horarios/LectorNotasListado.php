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
 * Las columnas se numeran desde 1 y las notas son sobre 10. Cada alumno es la línea
 * anterior a un correo, y sus notas, las líneas de detrás mientras sean «-» o un número;
 * así la media del final no cuenta.
 *
 * Si delante del primer alumno vienen las cabeceras, una por línea, las últimas son los
 * nombres de las columnas de notas (las primeras son nombre, correo…), con el tipo de
 * actividad pegado delante: «ProbaExamen TEST - UD1», «TarefaExamen UD1 - Cuestiones».
 * Sin cabeceras, la columna se llama por su número.
 */
class LectorNotasListado
{
    public const MAXIMO = 10;

    private const CORREO = '/^\S+@\S+\.\S+$/u';

    /** El tipo de actividad pegado delante del nombre de la columna (sin espacio detrás). */
    private const TIPO = '/^(Proba|Tarefa|Ardora|Cuestionario|Tarea|Prueba|Lección|Taller|Obradoiro|Foro)(?=[\p{Lu}\[])/u';

    /**
     * @return array{columnas: array<int, string>, filas: array<int, array{texto: string, valores: array<int, ?float>}>}
     *                                                                                                                   'columnas': las que traen alguna nota, con su nombre.
     */
    public function leer(string $texto): array
    {
        $lineas = array_values(array_filter(
            array_map(fn ($linea) => trim(preg_replace('/\s+/u', ' ', $linea)), preg_split('/\R/u', $texto)),
            fn ($linea) => $linea !== ''
        ));

        $filas = [];
        $primera = null;
        foreach ($lineas as $i => $linea) {
            if ($i === 0 || ! preg_match(self::CORREO, $linea) || preg_match(self::CORREO, $lineas[$i - 1])) {
                continue;
            }

            $valores = [];
            for ($j = $i + 1; isset($lineas[$j]) && $this->esValor($lineas[$j]); $j++) {
                // La primera columna es la 1.
                $valores[$j - $i] = $lineas[$j] === '-' ? null : (float) str_replace(',', '.', $lineas[$j]);
            }

            $primera ??= $i - 1;
            $filas[] = ['texto' => $lineas[$i - 1], 'valores' => $valores];
        }

        // Las cabeceras: tantas líneas como columnas de notas, justo antes del primer alumno.
        $total = $filas ? max(array_map(fn (array $fila) => count($fila['valores']), $filas)) : 0;
        $cabeceras = $total && $primera >= $total ? array_slice($lineas, $primera - $total, $total) : [];

        $columnas = [];
        foreach ($filas as $fila) {
            foreach ($fila['valores'] as $columna => $valor) {
                if ($valor !== null) {
                    $columnas[$columna] = isset($cabeceras[$columna - 1])
                        ? trim(preg_replace(self::TIPO, '', $cabeceras[$columna - 1]))
                        : __('Columna :numero', ['numero' => $columna]);
                }
            }
        }
        ksort($columnas);

        return ['columnas' => $columnas, 'filas' => $filas];
    }

    private function esValor(string $linea): bool
    {
        return $linea === '-' || preg_match('/^\d+([.,]\d+)?$/', $linea);
    }
}
