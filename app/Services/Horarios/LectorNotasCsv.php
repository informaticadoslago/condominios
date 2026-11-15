<?php

namespace App\Services\Horarios;

/**
 * Saca las notas de un CSV pegado como texto, con una primera línea de cabeceras y una
 * línea por alumno:
 *
 *   Apelido(s),Nome,...,"Cualificación/10,00","P. 1 /0,33",...
 *   "Domínguez López",Anxo,...,"8,50","0,33",...
 *
 * Solo usa tres columnas, que busca por su cabecera: apellidos, nombre y calificación.
 * La puntuación máxima sale de la cabecera de la calificación («/10,00»). Las líneas sin
 * nombre (la de la media) se saltan.
 */
class LectorNotasCsv
{
    private const APELLIDOS = '/^apel+idos?\b/iu';

    private const NOMBRE = '/^nom(e|bre)$/iu';

    private const CALIFICACION = '#^(?:cuali|cali)ficaci[oó]n\s*/\s*([\d.,]+)$#iu';

    /**
     * @return array{maximo: ?float, filas: array<int, array{apellidos: string, nombre: string, nota: ?float, error: ?string}>}
     *                                                                                                 'maximo' null: no se han encontrado las cabeceras.
     */
    public function leer(string $texto): array
    {
        $lineas = $this->lineas($texto);
        $cabecera = array_map(fn ($c) => trim((string) $c), array_shift($lineas) ?? []);

        $colApellidos = $colNombre = $colNota = $maximo = null;
        foreach ($cabecera as $i => $titulo) {
            if ($colApellidos === null && preg_match(self::APELLIDOS, $titulo)) {
                $colApellidos = $i;
            } elseif ($colNombre === null && preg_match(self::NOMBRE, $titulo)) {
                $colNombre = $i;
            } elseif ($colNota === null && preg_match(self::CALIFICACION, $titulo, $m)) {
                $colNota = $i;
                $maximo = $this->numero($m[1]);
            }
        }

        if ($colApellidos === null || $colNombre === null || $colNota === null || ! $maximo) {
            return ['maximo' => null, 'filas' => []];
        }

        $filas = [];
        foreach ($lineas as $linea) {
            $apellidos = $this->limpiar($linea[$colApellidos] ?? '');
            $nombre = $this->limpiar($linea[$colNombre] ?? '');

            if ($apellidos === '' || $nombre === '') {
                continue;
            }

            $nota = $this->numero((string) ($linea[$colNota] ?? ''));
            $filas[] = [
                'apellidos' => $apellidos,
                'nombre'    => $nombre,
                'nota'      => $nota,
                'error'     => match (true) {
                    $nota === null                => __('Sin nota'),
                    $nota < 0 || $nota > $maximo => __('Nota fuera de rango'),
                    default                       => null,
                },
            ];
        }

        return ['maximo' => $maximo, 'filas' => $filas];
    }

    /** Las líneas del CSV, ya partidas en campos; sin las vacías. */
    private function lineas(string $texto): array
    {
        $flujo = fopen('php://temp', 'r+');
        fwrite($flujo, preg_replace('/^\x{FEFF}/u', '', $texto));
        rewind($flujo);

        $lineas = [];
        while (($campos = fgetcsv($flujo, null, ',', '"', '')) !== false) {
            if ($campos !== [null]) {
                $lineas[] = $campos;
            }
        }
        fclose($flujo);

        return $lineas;
    }

    private function limpiar(?string $texto): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $texto));
    }

    /** «8,50» → 8.5; lo que no sea un número («-», vacío) → null. */
    private function numero(string $texto): ?float
    {
        $texto = str_replace(',', '.', trim($texto));

        return is_numeric($texto) ? (float) $texto : null;
    }
}
